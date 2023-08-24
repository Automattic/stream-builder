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

use function is_null;
use function time;

/**
 * A CacheProvider that just stores into an in-memory array. Mostly for testing!
 */
final class TransientCacheProvider implements CacheProvider
{
    /** @var int */
    private $default_ttl_seconds;

    /** @var array[][] 2-D Array of [object_type][key] -> [ expiration, value ] */
    private $caches;

    /**
     * @param int $default_ttl_seconds The default TTL to use when none is provided to a set operation.
     */
    public function __construct(int $default_ttl_seconds = SECONDS_PER_MINUTE)
    {
        $this->caches = [];
        $this->default_ttl_seconds = $default_ttl_seconds;
    }

    /**
     * @inheritDoc
     */
    public function get(int $object_type, string $key)
    {
        [$expiration, $value] = $this->caches[$object_type][$key] ?? [0, null];
        return ($expiration >= time()) ? $value : null;
    }

    /**
     * @inheritDoc
     */
    public function get_multi(int $object_type, array $keys, array &$not_found = []): array
    {
        $found = [];
        $not_found = [];
        foreach ($keys as $key) {
            $val = $this->get($object_type, $key);
            if (is_null($val)) {
                $not_found[$key] = $key;
            } else {
                $found[$key] = $val;
            }
        }
        return $found;
    }

    /**
     * @inheritDoc
     */
    public function set(int $object_type, string $key, $value, int $ttl_seconds = 0)
    {
        if ($ttl_seconds <= 0) {
            $ttl_seconds = $this->default_ttl_seconds;
        }
        $this->caches[$object_type][$key] = [time() + $ttl_seconds, $value];
    }

    /**
     * @inheritDoc
     */
    public function set_multi(int $object_type, array $key_value_pairs, int $ttl_seconds = 0)
    {
        foreach ($key_value_pairs as $key => $value) {
            $this->set($object_type, $key, $value, $ttl_seconds);
        }
    }
}
