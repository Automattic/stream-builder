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

use Tumblr\StreamBuilder\Exceptions\UncombinableCursorException;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;

/**
 * Class UncombinableCursorExceptionTest
 */
class UncombinableCursorExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor.
     */
    public function test_constructor()
    {
        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $sc_foo */
        $sc_foo = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sc_foo->expects($this->any())
            ->method('to_string')
            ->willReturn('sc_foo');

        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $sc_bar */
        $sc_bar = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sc_bar->expects($this->any())
            ->method('to_string')
            ->willReturn('sc_bar');

        $exception = new UncombinableCursorException($sc_foo, $sc_bar);
        $this->assertMatchesRegularExpression(
            '/Incompatible cursors\: \'sc_foo\' \(Mock_StreamCursor_.{8}\) cannot combine with \'sc_bar\' \(Mock_StreamCursor_.{8}\)/',
            $exception->getMessage()
        );
    }
}
