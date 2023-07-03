<?php
/**
 * The StreamBuilder framework.
 * Copyright 2023 Automattic, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Tumblr\StreamBuilder\Streams;

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use const Tumblr\StreamBuilder\SECONDS_PER_MINUTE;

/**
 * This stream will only enumerate the inner stream when cache misses.
 * It will cache whatever the inner stream enumerate results are.
 * And on cache hit, it will just return cached elements, sliced with offset and limit.
 *
 * As you can imagine, the cache key will need to include inner_stream hash, cursor (offset), enumerate count (limit),
 * and anything else that makes this stream "page" unique, to make this stream work for pagination.
 *
 * This is different from FencePostRankedStream. This stream doesn't have a `sequence` concept and the cached result
 * is one-to-one mapping with the composition of the cache key.
 *
 * Override _slice_result_with_cursor to handle cursor/pagination.
 *
 * Be careful about serialize / deserialize:
 * StreamResult->to_template() can easily become huge and need special handling/compression.
 * (Most cache implementations will have a value size limit.)
 */
abstract class CachedStream extends WrapStream
{
    /** @var string Used in templating */
    public const STREAM_COLUMN = 'inner';
    /** @var string Used in templating */
    public const CACHE_TTL_COLUMN = 'ttl';
    /** @var string Used in templating */
    public const CANDIDATE_COUNT_COLUMN = 'candidate_count';
    /** @var string Used in templating */
    public const CACHE_OBJECT_TYPE_COLUMN = 'object_type';

    /** @var CacheProvider The actual used cache. */
    protected $cache_provider;

    /** @var int Cache ttl. */
    protected $cache_ttl;

    /** @var int Cache object type. */
    protected $cache_object_type;

    /** @var int Cached elements count. */
    protected $candidate_count;

    /** @var string[] Components that composite the cache key to make it unique. */
    protected $cache_key_components;

    /**
     * CachedStream constructor.
     * @param Stream $inner_stream Inner stream that enumerates actual content.
     * @param CacheProvider $cache_provider Cache provider that corresponds to specific cache implementation.
     * @param int $cache_object_type The cache object type used in $cache_provider.
     * @param int $cache_ttl TTL for the cache.
     * @param int $candidate_count Cached elements count.
     * @param string $identity See Identifiable.
     * @param array $cache_key_components Other components that make cache key unique.
     */
    public function __construct(
        Stream $inner_stream,
        CacheProvider $cache_provider,
        int $cache_object_type,
        int $cache_ttl,
        int $candidate_count,
        string $identity,
        array $cache_key_components = []
    ) {
        parent::__construct($inner_stream, $identity);
        $this->cache_provider = $cache_provider;
        $this->cache_object_type = $cache_object_type;
        $this->cache_ttl = $cache_ttl;
        $this->candidate_count = $candidate_count;
        $this->cache_key_components = $cache_key_components;
    }

    /**
     * @inheritDoc
     */
    protected function _enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $t0 = microtime(true);
        $inner_cursor = $this->inner_cursor($cursor);
        // do not hit caching logic if ttl <= 0
        if ($this->cache_ttl <= 0) {
            $inner_result = $this->getInner()->enumerate($this->candidate_count, $inner_cursor, $tracer, $option);
            return $this->_slice_result_with_cursor($count, $inner_result, $cursor);
        }

        $cache_key = $this->getCacheKey($inner_cursor);
        if (is_null($cache_key)) {
            StreamBuilder::getDependencyBag()->getLog()
                ->rateTick('algodash_errors', 'cache_stream_invalid_key');
            // return inner result directly
            return $this->getInner()->enumerate($this->candidate_count, $inner_cursor, $tracer, $option);
        }

        // Step 1: Check if result is cached, if hit, early return.
        $cached_results = $this->cache_provider->get($this->cache_object_type, $cache_key);
        if (!empty($cached_results)) {
            $parsed_elements = $this->deserialize($cached_results);
            if ($parsed_elements instanceof StreamResult) {
                $tracer && $tracer->enumerate_cache_hit($this, [$t0, microtime(true) - $t0]);
                return $this->_slice_result_with_cursor($count, $parsed_elements, $cursor);
            }
        }

        // Step 2: Cache miss, do inner_stream enumeration.
        $inner_result = $this->fetch_inner_results($this->candidate_count, $inner_cursor, $tracer, $option);

        // Step 3: Cache the inner_stream enumeration result.
        $cache_value = $this->serialize($inner_result);
        if ($inner_result->get_size() === 0) {
            // make empty result's cache ttl shorter.
            // max at 15 min
            $ttl = min($this->cache_ttl / 4, SECONDS_PER_MINUTE * 15);
        } else {
            $ttl = $this->cache_ttl;
        }
        $this->cache_provider->set($this->cache_object_type, $cache_key, $cache_value, $ttl);

        // Step 4: Slice the result and serve the requested based on cursor.
        // we will still use the same cursor's offset to slice the newly fetched results.
        $res =  $this->_slice_result_with_cursor($count, $inner_result, $cursor);
        $tracer && $tracer->enumerate_cache_miss($this, [$t0, microtime(true) - $t0]);
        return $res;
    }

    /**
     * Unwrap the passed-in cursor for inner stream enumeration.
     * @param StreamCursor|null $cursor The passed in cursor.
     * @return StreamCursor|null The inner stream enumeration cursor.
     */
    abstract protected function inner_cursor(?StreamCursor $cursor): ?StreamCursor;

    /**
     * Slice the cached result with respect to the cursor.
     * @param int $count How many elements do you want?
     * @param StreamResult $inner_result The cached StreamResult.
     * @param StreamCursor|null $cursor The pagination cursor.
     * @return StreamResult
     */
    abstract protected function _slice_result_with_cursor(
        int $count,
        StreamResult $inner_result,
        StreamCursor $cursor = null
    ): StreamResult;

    /**
     * @param int $count Count
     * @param StreamCursor|null $cursor Cursor
     * @param StreamTracer|null $tracer Tracer
     * @param EnumerationOptions|null $options Options
     * @return StreamResult
     */
    protected function fetch_inner_results(
        int $count,
        ?StreamCursor $cursor,
        ?StreamTracer $tracer,
        ?EnumerationOptions $options
    ): StreamResult {
        return $this->getInner()->enumerate($count, $cursor, $tracer, $options);
    }

    /**
     * Get cache key for the current enumeration, which is md5() hashed.
     * The cache key composition is inner stream template and extra_components.
     * @param StreamCursor|null $cursor Cursor may need in stream result cache key
     * @return string|null
     */
    protected function getCacheKey(?StreamCursor $cursor = null): ?string
    {
        $inner_stream_str = Helpers::json_encode($this->getInner()->to_template());
        $extra_components = implode('::', $this->cache_key_components);
        return md5(sprintf('%s::%s::%s', $inner_stream_str, $this->candidate_count, $extra_components));
    }

    /**
     * Serialize the stream result to string, such that can be stored in cache.
     * BE CAREFUL ABOUT CACHE IMPLEMENTATION VALUE SIZE LIMIT!
     * @param StreamResult $result The inner stream enumeration result.
     * @return string
     */
    protected function serialize(StreamResult $result): string
    {
        return Helpers::json_encode($result->to_template());
    }

    /**
     * Deserialize the cached string back to StreamResult.
     * @param string $result The cached result.
     * @return StreamResult|null
     */
    public function deserialize(string $result): ?StreamResult
    {
        try {
            $json = Helpers::json_decode($result);
        } catch (\Exception $e) {
            return null;
        }
        $context = new StreamContext($json, []);
        return StreamResult::from_template($context);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base[self::STREAM_COLUMN] = $this->getInner()->to_template();
        $base[self::CACHE_TTL_COLUMN] = $this->cache_ttl;
        $base[self::CANDIDATE_COUNT_COLUMN] = $this->candidate_count;
        $base[self::CACHE_OBJECT_TYPE_COLUMN] = $this->cache_object_type;
        return $base;
    }
}
