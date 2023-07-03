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

/**
 * Exception thrown when we cannot parse the cursor string to array.
 */
final class InvalidCursorStringException extends \InvalidArgumentException
{
    /**
     * InvalidCursorSignatureException constructor.
     * @param string $cursor_string The invalid cursor string.
     */
    public function __construct(string $cursor_string)
    {
        parent::__construct(sprintf(
            'Encoded cursor string is invalid: %s',
            $cursor_string
        ));
    }
}
