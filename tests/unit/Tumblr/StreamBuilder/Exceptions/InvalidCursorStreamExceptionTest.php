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

namespace Test\Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\Exceptions\InvalidCursorStringException;

/**
 * Class InvalidCursorStreamExceptionTest
 *
 * @covers \Tumblr\StreamBuilder\Exceptions\InvalidCursorStringException
 */
class InvalidCursorStreamExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor.
     */
    public function test_constructor()
    {
        $invalid_cursor_string = 'an_invalid_string';

        $exception = new InvalidCursorStringException($invalid_cursor_string);
        $this->assertSame(
            'Encoded cursor string is invalid: an_invalid_string',
            $exception->getMessage()
        );
    }
}
