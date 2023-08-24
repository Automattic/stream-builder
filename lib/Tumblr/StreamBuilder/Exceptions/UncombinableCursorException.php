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

use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use function sprintf;
use function get_class;

/**
 * Exception thrown when an attempt is made to combine two cursors which cannot be combined.
 */
final class UncombinableCursorException extends \InvalidArgumentException
{
    /**
     * @param StreamCursor $base_cursor The cursor on which combine_with was called.
     * @param StreamCursor $other_cursor The cursor being combined.
     */
    public function __construct(StreamCursor $base_cursor, StreamCursor $other_cursor)
    {
        parent::__construct(sprintf(
            'Incompatible cursors: \'%s\' (%s) cannot combine with \'%s\' (%s)',
            $base_cursor,
            get_class($base_cursor),
            $other_cursor,
            get_class($other_cursor)
        ));
    }
}
