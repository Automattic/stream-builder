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

namespace Tumblr\StreamBuilder\StreamFilters;

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Exceptions\UncacheableStreamFilterException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function get_class;
use function array_fill;
use function count;
use function is_null;
use function sprintf;
use const Tumblr\StreamBuilder\SECONDS_PER_DAY;

/**
 * A CachedStreamFilter attempts to amortize the inflation cost of data models by caching the results of
 * an expensive filter. Because filters are assigned identities, this identity is used as part of a
 * caching key. The cached filter must be relatively stable or else both false-positives and
 * false-negatives are possible.
 *
 * The format of a key is:
 * ${filter_id}:${version}:${element_cache_key}
 *
 * The value of a key (if present) is either P or F, indicating whether the element
 * passes (P) or fails (F) the filter. This is done because our cache seems to swallow zeros :(.
 *
 * NOTE 1: Personalized (user-specific) filters must never be cached. Obviously, neither should
 * filters that are O(1) and require no external data, since those are performant enough without cache.
 * NOTE 3: This has non-zero overhead! ALWAYS contact the relevant parties before using.
 */
final class CachedStreamFilter extends StreamFilter
{
    /** @var StreamFilter */
    private $inner;
    /** @var string */
    private $version;
    /** @var int */
    private $ttl_seconds_retain;
    /** @var int */
    private $ttl_seconds_release;
    /** @var string|null */
    private $inner_cache_key;
    /** @var CacheProvider */
    private $cache_provider;

    /**
     * @param string $identity String identifying this element in the context of a stream topology.
     * @param StreamFilter $inner The filter to cache.
     * @param CacheProvider $cache_provider Provider of caching.
     * @param string $version Used for cache-busting.
     * @param int $ttl_seconds_retain Expiration lifetime of retained cached entries.
     * @param int $ttl_seconds_release Expiration lifetime of released cached entries.
     * @throws UncacheableStreamFilterException If the inner filter is not cacheable.
     */
    public function __construct(
        string $identity,
        StreamFilter $inner,
        CacheProvider $cache_provider,
        string $version,
        int $ttl_seconds_retain = 0,
        int $ttl_seconds_release = SECONDS_PER_DAY
    ) {
        parent::__construct($identity);
        $inner_cache_key = $inner->get_cache_key();
        if (is_null($inner_cache_key)) {
            throw new UncacheableStreamFilterException($inner);
        }
        $this->inner_cache_key = $inner_cache_key;
        $this->inner = $inner;
        $this->version = $version;
        $this->ttl_seconds_retain = $ttl_seconds_retain;
        $this->ttl_seconds_release = $ttl_seconds_release;
        $this->cache_provider = $cache_provider;
    }

    /**
     * @inheritDoc
     */
    public function get_cache_key()
    {
        return sprintf('Cached(%s,%s)', $this->inner_cache_key, $this->version);
    }

    /**
     * @inheritDoc
     */
    public function to_string(): string
    {
        return sprintf('Cached(%s)', $this->inner->to_string());
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            "_type" => get_class($this),
            "stream_filter" => $this->inner->to_template(),
            "version" => $this->version,
            "ttl_seconds_retain" => $this->ttl_seconds_retain,
            "ttl_seconds_release" => $this->ttl_seconds_release,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $filter = $context->deserialize_required_property('stream_filter');
        return new self(
            $context->get_current_identity(),
            $filter,
            $context->get_cache_provider(),
            $context->get_optional_property('version'),
            $context->get_optional_property('ttl_seconds_retain', 0),
            $context->get_optional_property('ttl_seconds_release', SECONDS_PER_DAY)
        );
    }

    /**
     * @inheritDoc
     */
    final public function filter_inner(array $elements, StreamFilterState $state = null, StreamTracer $tracer = null): StreamFilterResult
    {
        $key_to_index = [];
        $index_to_key = [];

        /** @val StreamElement $e */
        foreach ($elements as $i => $e) {
            $k = $this->cache_key($e);
            if ($k === null) {
                continue;
            }
            $key_to_index[$k] = $i;
            $index_to_key[$i] = $k;
        }

        $not_found_keys = []; // a subset of index_to_key (orig_index => not_found_key)
        $found_keys = $this->cache_provider->get_multi(CacheProvider::OBJECT_TYPE_FILTER, $index_to_key, $not_found_keys); // key => value in the same order as input.

        $output_elements = array_fill(0, count($elements), null);

        foreach ($found_keys as $k => $v) {
            if ($v == 'P') {
                $output_elements[$key_to_index[$k]] = $elements[$key_to_index[$k]];
            }
        }

        if ($not_found_keys) {

            $not_found_elems = [];
            foreach ($not_found_keys as $k) {
                $not_found_elems[] = $elements[$key_to_index[$k]];
            }

            $inner_result = $this->inner->filter($not_found_elems, $state);

            $to_cache_retain = [];
            foreach ($inner_result->get_retained() as $e) {
                $k = $this->cache_key($e);
                if (!is_null($k)) {
                    // some specific elements may be uncacheable, even though the filter itself is cacheable.
                    $to_cache_retain[$k] = 'P';
                }
                $output_elements[$key_to_index[$k]] = $elements[$key_to_index[$k]];
            }
            if ($this->ttl_seconds_retain > 0) {
                $this->cache_provider->set_multi(CacheProvider::OBJECT_TYPE_FILTER, $to_cache_retain, $this->ttl_seconds_retain);
            }

            $to_cache_release = [];
            foreach ($inner_result->get_released() as $e) {
                $k = $this->cache_key($e);
                if (!is_null($k)) {
                    // some specific elements may be uncacheable, even though the filter itself is cacheable.
                    $to_cache_release[$k] = 'F';
                }
            }
            $this->cache_provider->set_multi(CacheProvider::OBJECT_TYPE_FILTER, $to_cache_release, $this->ttl_seconds_release);
        }

        $result_retain = [];
        $result_release = [];
        foreach ($output_elements as $i => $e) {
            if ($e) {
                $result_retain[] = $e;
            } else {
                $result_release[] = $elements[$i];
            }
        }

        // NOTE: discards state here! When caching, filter state is nonsense.
        return StreamFilterResult::create_from_leaf_filter($result_retain, $result_release);
    }

    /**
     * Generate the cache key for storing metadata about a StreamElement.
     * @param StreamElement $e The element.
     * @return string|null The cache key, combining the filter, version, and element's cache_key.
     */
    private function cache_key(StreamElement $e)
    {
        $ck = $e->get_cache_key();
        if (is_null($ck)) {
            return null; // do not cache results for this element
        }
        return sprintf('%s:%s:%s', $this->inner_cache_key, $this->version, $ck);
    }
}
