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
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\BufferedCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A stream which overfetches and ranks another stream, remembering unused items in a buffer for future pages.
 */
final class BufferedRankedStream extends Stream
{
    /** @var Stream */
    private $inner;
    /** @var StreamRanker */
    private $ranker;
    /** @var int */
    private $overfetch_count;
    /** @var CacheProvider */
    private $cache_provider;

    /**
     * @param Stream $inner The inner stream to rank.
     * @param StreamRanker $ranker Ranker used to order elements.
     * @param int $overfetch_count How many items to buffer.
     * @param string $identity The identity of this stream.
     * @param CacheProvider $cache_provider Provider of caching.
     */
    public function __construct(Stream $inner, StreamRanker $ranker, int $overfetch_count, string $identity, CacheProvider $cache_provider)
    {
        parent::__construct($identity);
        $this->inner = $inner;
        $this->ranker = $ranker;
        $this->overfetch_count = $overfetch_count;
        $this->cache_provider = $cache_provider;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function _enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        if (is_null($cursor)) {
            $cursor = new BufferedCursor(null, [], $this->cache_provider);
        } elseif (!($cursor instanceof BufferedCursor)) {
            throw new InappropriateCursorException($this, $cursor);
        }
        /** @var BufferedCursor $cursor */
        $pool = $cursor->get_buffer();
        $inner_cursor = $cursor->get_inner_cursor();

        $pool_deficit = ($count + $this->overfetch_count) - count($pool);
        if ($pool_deficit > 0) {
            // need to refill!
            $refill_result = $this->inner->enumerate($pool_deficit, $inner_cursor, $tracer);
            $refill_elements = $refill_result->get_elements();
            $inner_cursor = $refill_result->get_combined_cursor();
            $pool = array_merge($pool, $refill_elements);
        }

        $ranked_elems = $this->ranker->rank($pool, $tracer);

        /** @var StreamElement[] $derived_elems */
        $derived_elems = [];
        $return_count = min($count, count($ranked_elems));
        for ($i = 0; $i < $return_count; $i++) {
            $elem = $ranked_elems[$i];
            $cur = new BufferedCursor($inner_cursor, Helpers::omit_element($ranked_elems, $elem), $this->cache_provider);
            $derived_elems[$i] = new DerivedStreamElement($elem, $this->get_identity(), $cur);
        }
        return new StreamResult(count($derived_elems) < $count, $derived_elems);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'inner' => $this->inner->to_template(),
            'ranker' => $this->ranker->to_template(),
            'overfetch_count' => $this->overfetch_count,
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        return new self(
            $context->deserialize_required_property('inner'),
            $context->deserialize_required_property('ranker'),
            $context->get_required_property('overfetch_count'),
            $context->get_current_identity(),
            $context->get_cache_provider()
        );
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function can_enumerate(): bool
    {
        return parent::can_enumerate() && $this->inner->can_enumerate();
    }
}
