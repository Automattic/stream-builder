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

namespace Tumblr\StreamBuilder\Exceptions;

use function sprintf;

/**
 * Exception thrown when we are not able to fetch cache.
 */
final class MissingCacheException extends \RuntimeException
{
    /** @var string */
    private $cache_key;
    /** @var int */
    private $object_type;

    /**
     * MissingCacheException constructor.
     * @param int $object_type The object type being looked up.
     * @param string $cache_key The key of the cache record.
     */
    public function __construct(int $object_type, string $cache_key)
    {
        parent::__construct(sprintf(
            'Cache key(%s) cannot be found for object type %d',
            $cache_key,
            $object_type
        ));
        $this->cache_key = $cache_key;
        $this->object_type = $object_type;
    }

    /**
     * To get cache key.
     * @return string
     */
    public function get_cache_key(): string
    {
        return $this->cache_key;
    }

    /**
     * @return int
     */
    public function get_object_type(): int
    {
        return $this->object_type;
    }
}
