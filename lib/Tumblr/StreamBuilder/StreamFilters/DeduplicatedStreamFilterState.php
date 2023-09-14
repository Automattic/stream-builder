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
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamFilterState;

/**
 * State for the DeduplicatedStreamFilter
 * @see DeduplicatedStreamFilter
 */
class DeduplicatedStreamFilterState extends StreamFilterState
{
    /**
     * Cache key prefix for deduplicated stream filter state
     */
    public const CACHE_KEY_PREFIX = 'dedup_';

    /**
     * @var array<string, array-key> $seen_items_set Hash-set of element ids; the keys are important, the values are
     * not. Because PHP hashes are insertion-ordered, later items were seen "more recently".
     */
    private $seen_items_set;

    /**
     * @var int $size
     */
    private $size;

    /**
     * @var CacheProvider
     */
    private $cache_provider;

    /**
     * @param int $size Maximum number of items to store.
     * @param string[] $seen_items Ids of seen items, as array values, in LRU-MRU order.
     * @param CacheProvider $cache_provider The provider of caching.
     * @throws TypeMismatchException If $size is not an integer.
     * @throws \InvalidArgumentException If $size is not positive.
     */
    public function __construct(int $size, ?array $seen_items, CacheProvider $cache_provider)
    {
        $seen_items = $seen_items ?? [];
        if ($size < 1) {
            throw new \InvalidArgumentException('size must be positive');
        }
        $this->size = $size;
        $this->cache_provider = $cache_provider;
        $this->seen_items_set = array_flip(array_slice($seen_items, -$size));
    }

    /**
     * Get the seen items, as LRU-MRU ordered cache keys.
     * @return string[]
     */
    public function get_seen_items(): array
    {
        return array_keys($this->seen_items_set);
    }

    /**
     * @inheritDoc
     */
    protected function _can_merge_with(StreamFilterState $other): bool
    {
        return ($other instanceof DeduplicatedStreamFilterState);
    }

    /**
     * @inheritDoc
     */
    protected function _merge_with(StreamFilterState $other): StreamFilterState
    {
        /** @var DeduplicatedStreamFilterState $other */
        $output_size = max($this->size, $other->size);
        return static::multi_merge($output_size, $this->cache_provider, [$this, $other]);
    }

    /**
     * Include a new element in the memory. If the element is already in the memory, this is a noop!
     * @param string $element_cache_key Cache key of the element to be included in the state.
     * @return DeduplicatedStreamFilterState
     */
    public function with_new_item($element_cache_key): self
    {
        if (isset($this->seen_items_set[$element_cache_key])) {
            return $this;
        } else {
            $new_items_set = array_keys($this->seen_items_set);
            $new_items_set[] = $element_cache_key;
            return new self($this->size, $new_items_set, $this->cache_provider);
        }
    }

    /**
     * Test if an element has been seen before.
     * @param string $element_cache_key The cache key of the element to test.
     * @return bool True if the cache key is known to this state.
     */
    public function contains_item($element_cache_key): bool
    {
        return isset($this->seen_items_set[$element_cache_key]);
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('Dedup(%s)', implode(',', array_keys($this->seen_items_set)));
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $output = [
            '_type' => get_class($this),
            's' => $this->size,
        ];
        if (!empty($this->seen_items_set)) {
            // base64 reserves `=`, `/`,  and `+`, so we'll use `.` as a delimiter.
            $packed_items = implode('.', array_keys($this->seen_items_set));
            $cache_key = sprintf('%s%s', static::CACHE_KEY_PREFIX, md5($packed_items));

            $this->cache_provider->set(CacheProvider::OBJECT_TYPE_DEDUPLICATED_FILTER_STATE_MEMORY, $cache_key, $packed_items);
            $output['c'] = $cache_key;
        }
        return $output;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        // base64 reserves `=`, `/`,  and `+`, so we'll use `.` as a delimiter.
        $template = $context->get_template();
        $cache_provider = $context->get_cache_provider();
        $unpacked_items = [];

        if ($packed_items = $template['i'] ?? null) {
            $unpacked_items = explode('.', $packed_items);
        } elseif ($cache_key = $template['c'] ?? null) {
            if ($packed_items = $cache_provider->get(CacheProvider::OBJECT_TYPE_DEDUPLICATED_FILTER_STATE_MEMORY, $cache_key)) {
                $unpacked_items = explode('.', $packed_items);
            } else {
                StreamBuilder::getDependencyBag()->getLog()
                    ->rateTick('algodash_errors', 'dedupe_filter_state_key_not_found');
            }
        }
        return new self(
            intval($template['s'] ?? 100),
            $unpacked_items,
            $cache_provider
        );
    }

    /**
     * Merge multiple states together, preferring MRU items.
     * @param int $size Desired output size.
     * @param CacheProvider $cache_provider The cache provider to use for the combined state.
     * @param DeduplicatedStreamFilterState[] $states Array of input states to merge.
     * @return DeduplicatedStreamFilterState Merged state, preferring recently-returned items.
     */
    private static function multi_merge($size, CacheProvider $cache_provider, array $states): self
    {
        if (empty($states)) {
            return new DeduplicatedStreamFilterState($size, [], $cache_provider);
        } elseif (count($states) == 1) {
            return $states[0];
        } else {
            $max_offsets = [];
            foreach ($states as $state) {
                // in mru-lru order:
                $mru_lru = array_reverse(array_keys($state->seen_items_set));
                foreach ($mru_lru as $offset => $id) {
                    if ((!isset($max_offsets[$id])) || ($offset > $max_offsets[$id])) {
                        $max_offsets[$id] = $offset;
                    }
                }
            }
            asort($max_offsets);
            return new self($size, array_reverse(array_slice(array_keys($max_offsets), 0, $size)), $cache_provider);
        }
    }
}
