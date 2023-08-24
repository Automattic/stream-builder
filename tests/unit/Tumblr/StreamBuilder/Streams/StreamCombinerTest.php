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

use Test\Tumblr\StreamBuilder\StreamCursors\MockMaxCursor;
use Test\Tumblr\StreamBuilder\StreamElements\MockMaxStreamElement;
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamCursors\ConcatenatedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\StreamCombiner;
use function array_map;

/**
 * Class StreamCombinerTest
 */
class StreamCombinerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test enumerate
     */
    public function test_enumerate()
    {
        $e1 = new MockMaxStreamElement(1, 'p1', new MockMaxCursor(1));
        $e2 = new MockMaxStreamElement(2, 'p2', new MockMaxCursor(2));

        /** @var StreamCombiner|\PHPUnit\Framework\MockObject\MockObject $combiner */
        $combiner = $this->getMockBuilder(StreamCombiner::class)
            ->setMethods(['combine'])->setConstructorArgs(['amazing_combiner'])->getMockForAbstractClass();
        $combiner->expects($this->once())
            ->method('combine')
            ->with(10, new MultiCursor([]), null)
            ->willReturn(new StreamResult(true, [$e1, $e2]));

        $result = $combiner->enumerate(10);
        $this->assertSame([$e1, $e2], array_map(function (DerivedStreamElement $e) {
            return $e->get_original_element();
        }, $result->get_elements()));

        return $combiner;
    }

    /**
     * Test enumerate with wrong cursor.
     * @depends test_enumerate
     * @param StreamCombiner $stream The stream to be tested.
     */
    public function test_enumerate_wrong_cursor(StreamCombiner $stream)
    {
        $this->expectException(InappropriateCursorException::class);
        $stream->enumerate(10, new ConcatenatedStreamCursor(1));
    }
}
