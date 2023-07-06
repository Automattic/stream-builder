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

use Tumblr\StreamBuilder\StreamFilterState;
use function sprintf;
use function get_class;

/**
 * Exception thrown when an attempt is made to merge two filter states which cannot be merged.
 */
final class UnmergeableFilterStateException extends \InvalidArgumentException
{
    /**
     * @param StreamFilterState $base The filter state on which merge_with was called.
     * @param StreamFilterState $other The filter state being merged.
     */
    public function __construct(StreamFilterState $base, StreamFilterState $other)
    {
        parent::__construct(sprintf(
            'Incompatible filter states: \'%s\' (%s) cannot merge with \'%s\' (%s)',
            $base,
            get_class($base),
            $other,
            get_class($other)
        ));
    }
}
