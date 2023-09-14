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

namespace Tumblr\StreamBuilder;

/**
 * Abstraction over things that provide ephemeral key-value stores.
 * Keys are assumed to be strings, but values are mixed types. The value `null` is special
 * and represents a missing key.
 *
 * This class exists to make StreamBuilder not Tumblr-specific, so you can "BYOC" and use the stream
 * building framework regardless of how (or if) you use caching.
 *
 * Because caches are ephemeral, values stored MUST NEVER BE EXPECTED TO BE RETRIEVABLE... i.e. a
 * call to set() followed immediately by a call to get() might still return null.
 *
 * Implementors are required to handle the marshalling of various value types (integers, strings,
 * arrays, object, etc) to and from cache.
 */
interface CacheProvider
{
    /**
     * @var int Indicates the value being cached is the result of a CachedStreamFilter.
     */
    public const OBJECT_TYPE_FILTER = 1;

    /**
     * @var int Indicates the value being cached is a StreamCursor.
     */
    public const OBJECT_TYPE_CURSOR = 2;

    /**
     * @var int Indicates the value being cached is a DeduplicatedStreamFilterState's seen items set.
     */
    public const OBJECT_TYPE_DEDUPLICATED_FILTER_STATE_MEMORY = 3;

    /**
     * @var int Indicates the value being cached is an array of buffered stream elements
     */
    public const OBJECT_TYPE_BUFFERED_STREAM_ELEMENTS = 4;

    /**
     * @var int Indicates the value being cached is a fencepost
     */
    public const OBJECT_TYPE_FENCEPOST = 5;

    /**
     * @var int Indicates the value being cached is a fencepost id
     */
    public const OBJECT_TYPE_FENCEPOST_ID = 6;

    /**
     * @var int Indicates the value being cached is a timestamp (fencepost epoch)
     */
    public const OBJECT_TYPE_FENCEPOST_EPOCH = 11;

    /**
     * Get the value for the given key from the specified cache.
     * @param int $object_type The type of object being cached, use one of the OBJECT_TYPE_* constants.
     * @param string $key The key to look up.
     * @return mixed|null The cached value for the given key, or null if not found.
     */
    public function get(int $object_type, string $key);

    /**
     * Get the values for the given keys from the specified cache.
     * @param int $object_type The type of object being cached, use one of the OBJECT_TYPE_* constants.
     * @param string[] $keys The keys to look up, as array values. The keys of this array are ignored.
     * @param string[] $not_found Reference to an array to be populated with the set of missing keys,
     * as BOTH keys and values (i.e. [ 'key1' => 'key1', 'key2' => 'key2' ])
     * @return mixed[] Array mapping found keys to their respective values. Missing keys will NOT be
     * present in the returned array.
     */
    public function get_multi(int $object_type, array $keys, array &$not_found = []): array;

    /**
     * Set the value for the given key in the specified cache.
     * @param int $object_type The type of object being cached, use one of the OBJECT_TYPE_* constants.
     * @param string $key The key to set.
     * @param mixed $value The value to set for the key.
     * @param int $ttl_seconds Number of seconds for which the value is valid. If zero or negative, a default is used.
     * @return void
     */
    public function set(int $object_type, string $key, $value, int $ttl_seconds = 0);

    /**
     * Set the value for the given key in the specified cache.
     * @param int $object_type The type of object being cached, use one of the OBJECT_TYPE_* constants.
     * @param mixed[] $key_value_pairs Array mapping string keys to (mixed) values.
     * @param int $ttl_seconds Number of seconds for which the values are valid. If zero or negative, a default is used.
     * @return void
     */
    public function set_multi(int $object_type, array $key_value_pairs, int $ttl_seconds = 0);

    /**
     * Delete the key-value pair for a given key in the specified cache.
     * @param int $object_type The type of object being cached, use of the OBJECT_TYPE_* constants.
     * @param string $key
     * @return mixed
     */
    public function delete(int $object_type, string $key);
}
