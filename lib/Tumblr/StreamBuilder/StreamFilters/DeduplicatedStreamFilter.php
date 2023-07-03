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
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * This removes duplicated stream elements based on their cache keys.
 * Note: This is a stateful filter, behaviors change across different pages.
 */
class DeduplicatedStreamFilter extends StreamFilter
{
    /** @var int */
    private $window_size;
    /** @var CacheProvider */
    private $cache_provider;

    /**
     * DeduplicatedStreamFilter constructor.
     * @param string $identity String identifying this element in the context of a stream topology.
     * @param int $window_size The sliding window size indicates a max range of memory elements are counted for de-dupe.
     * @param CacheProvider $cache_provider The cache provider used for state storage.
     */
    public function __construct(string $identity, int $window_size, CacheProvider $cache_provider)
    {
        parent::__construct($identity);
        $this->window_size = max(0, $window_size);
        $this->cache_provider = $cache_provider;
    }

    /** @inheritDoc */
    public function get_cache_key()
    {
        return null;
    }

    /** @inheritDoc */
    public function get_state_id()
    {
        return 'Dedupe';
    }

    /**
     * @inheritDoc
     */
    final public function filter_inner(array $elements, StreamFilterState $state = null, StreamTracer $tracer = null): StreamFilterResult
    {
        /** @var DeduplicatedStreamFilterState $state */
        if (is_null($state)) {
            $state = new DeduplicatedStreamFilterState($this->window_size, [], $this->cache_provider);
        } elseif (!($state instanceof DeduplicatedStreamFilterState)) {
            throw new TypeMismatchException(DeduplicatedStreamFilterState::class, $state);
        }

        $retained = [];
        $released = [];
        $filter_states = [];
        $seen_keys = [];
        foreach ($elements as $el) {
            /** @var StreamElement $el */
            $element_keys = $this->get_dedup_ids($el);
            if (empty($element_keys)) {
                // cant dedupe things that dont have cache keys:
                $retained[] = $el;
                continue;
            }
            $is_dup = false;
            foreach ($element_keys as $element_key) {
                $filter_states[$element_key] = $state->with_new_item($element_key);
                if ($state->contains_item($element_key) || isset($seen_keys[$element_key])) {
                    $released[] = $el;
                    $is_dup = true;
                    break;
                }
                $seen_keys[$element_key] = true;
            }
            if (!$is_dup) {
                $retained[] = $el;
            }
        }
        return StreamFilterResult::create_from_leaf_filter($retained, $released, $filter_states);
    }

    /**
     * @param StreamElement $e The stream element that need to be dedup among others.
     * @return array The identifiers for dedup.
     */
    public function get_dedup_ids(StreamElement $e): array
    {
        $cache_key = $e->get_cache_key();
        if ($cache_key === null) {
            return [];
        }
        return [$cache_key];
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'window' => $this->window_size,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        $window_size = $context->get_optional_property('window', 100);
        return new self($context->get_current_identity(), $window_size, $context->get_cache_provider());
    }
}
