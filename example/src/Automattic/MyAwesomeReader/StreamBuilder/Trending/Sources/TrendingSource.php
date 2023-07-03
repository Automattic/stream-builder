<?php declare(strict_types=1);

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

namespace Automattic\MyAwesomeReader\StreamBuilder\Trending\Sources;

/**
 * A source that provides trending posts.
 */
class TrendingSource
{
    /**
     * Retrieves from db/cache the trending posts.
     * @return array The trending posts.
     */
    public static function getTrendingPosts(): array
    {
        return [
            'first topic',
            'second topic',
            'third topic',
            'fourth topic',
            'fifth topic',
        ];
    }
}
