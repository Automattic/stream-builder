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

namespace Tumblr\StreamBuilder\EnumerationOptions;

/**
 * The options we apply to optimize enumeration behavior, extended with an array of ids to filter.
 */
class EnumerationPreFilteringOptions extends EnumerationOptions
{
    /**
     * @var array Array of [id]
     */
    private array $ids_to_filter;

    /**
     * @param array $ids_to_filter Array of [id]
     * @param int|null $max_ts_inclusive Maximum Timestamp inclusive for enumeration
     * @param int|null $min_ts_exclusive Minimum Timestamp inclusive for enumeration
     */
    public function __construct(array $ids_to_filter, ?int $max_ts_inclusive, ?int $min_ts_exclusive)
    {
        parent::__construct($max_ts_inclusive, $min_ts_exclusive);
        $this->ids_to_filter = $ids_to_filter;
    }

    /**
     * @return array Array of [id]
     */
    public function get_ids_to_filter(): array
    {
        return $this->ids_to_filter;
    }
}
