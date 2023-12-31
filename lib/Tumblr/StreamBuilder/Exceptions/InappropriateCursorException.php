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
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Exception thrown when a cursor is provided for stream enumeration, but the stream
 * cannot meaningfully interpret the cursor.
 */
final class InappropriateCursorException extends \InvalidArgumentException
{
    /**
     * @param Stream $stream The stream which was enumerated.
     * @param StreamCursor $cursor The invalid cursor.
     */
    public function __construct(Stream $stream, StreamCursor $cursor)
    {
        parent::__construct(sprintf(
            'Inappropriate cursor: \'%s\' (%s) cannot interpret \'%s\' (%s)',
            $stream->get_identity(),
            get_class($stream),
            $cursor,
            get_class($cursor)
        ));
    }
}
