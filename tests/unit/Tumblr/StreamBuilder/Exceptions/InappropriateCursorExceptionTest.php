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

use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class InappropriateCursorExceptionTest
 */
class InappropriateCursorExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test Constructor
     * @return void
     */
    public function test_constructor()
    {
        /** @var Stream $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['awesome_id'])
            ->getMock();

        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $sc */
        $sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $sc->expects($this->any())
            ->method('to_string')
            ->willReturn('sc');

        $exception = new InappropriateCursorException($stream, $sc);
        $this->assertMatchesRegularExpression(
            '/Inappropriate cursor: \'awesome_id\' \(Mock_Stream_.{8}\) cannot interpret \'sc\' \(Mock_StreamCursor_.{8}\)/',
            $exception->getMessage()
        );
    }
}
