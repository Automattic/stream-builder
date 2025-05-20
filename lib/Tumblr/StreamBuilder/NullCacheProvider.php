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
 * A CacheProvider that forgets everything you put in it. By definition,
 * this does not violate the contract of a CacheProvider, it is just far more ephemeral than most real-world caches.
 * Useful for exposing logical bugs!
 */
final class NullCacheProvider implements CacheProvider
{
    /**
     * @inheritDoc
     */
    #[\Override]
    public function get(int $object_type, string $key)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function get_multi(int $object_type, array $keys, array &$not_found = []): array
    {
        $not_found = $keys;
        return [];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function set(int $object_type, string $key, $value, int $ttl_seconds = 0)
    {
        // nope, not setting
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function set_multi(int $object_type, array $key_value_pairs, int $ttl_seconds = 0)
    {
        // bye, not setting
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function delete(int $object_type, string $key)
    {
        // no-op
    }
}
