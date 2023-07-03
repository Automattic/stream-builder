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

use Tumblr\StreamBuilder\StreamFilters\StreamFilter;

/**
 * Exception thrown when an uncacheable stream filter is used in a context where a cacheable stream filter is required.
 */
final class UncacheableStreamFilterException extends \LogicException
{
    /**
     * @param StreamFilter $sf The uncacheable stream filter.
     */
    public function __construct(StreamFilter $sf)
    {
        parent::__construct(sprintf('Uncacheable stream filter: %s', get_class($sf)));
    }
}
