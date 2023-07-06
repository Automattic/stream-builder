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

use function is_object;
use function get_class;
use function gettype;
use function sprintf;

/**
 * An exception thrown when the type of an argument (or value) falls outside an allowable domain.
 */
final class TypeMismatchException extends \InvalidArgumentException
{
    /**
     * Note: This does not do any type-checking. You need to do that yourself to decide if you want to throw this exception.
     * @param string $expected_type The expected value type. Suffix with a question mark if nullable.
     * @param mixed $received_argument The actual value.
     */
    public function __construct($expected_type, $received_argument)
    {
        $received_type = 'empty';
        if (!empty($received_argument)) {
            $received_type = is_object($received_argument) ? get_class($received_argument) : gettype($received_argument);
        }

        parent::__construct(sprintf(
            'Expected \'%s\', but received \'%s\'',
            $expected_type,
            $received_type
        ));
    }
}
