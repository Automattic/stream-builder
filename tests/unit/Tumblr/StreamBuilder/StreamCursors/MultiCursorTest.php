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

namespace Test\Tumblr\StreamBuilder\StreamCursors;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\FilteredStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class MultiCursorTest
 */
class MultiCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     */
    public function test_to_template()
    {
        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $foo_cursor */
        $foo_cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $foo_cursor->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'amazing_foo',
                'foo' => 33,
            ]);

        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $bar_cursor */
        $bar_cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $bar_cursor->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'amazing_bar',
                'bar' => 44,
            ]);

        $cursor = new MultiCursor([
            'foo' => $foo_cursor,
            'bar' => $bar_cursor,
        ], [
            'next_offset' => 22,
        ]);
        $this->assertSame($cursor->to_template(), [
            '_type' => MultiCursor::class,
            's' => [
                'foo' => [
                    '_type' => 'amazing_foo',
                    'foo' => 33,
                ],
                'bar' => [
                    '_type' => 'amazing_bar',
                    'bar' => 44,
                ],
            ],
            'i' => [
                'next_offset' => 22,
            ],
        ]);
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => MultiCursor::class,
            's' => [
                'Filtered(kkk)' => [
                    '_type' => FilteredStreamCursor::class,
                ],
            ],
            'i' => [
                'next_offset' => 34,
            ],
        ];
        $context = new StreamContext($template, []);
        $cursor = MultiCursor::from_template($context);
        $this->assertSame(
            (string) $cursor,
            'Multi(Filtered(kkk):Filtered(,))'
        );
    }

    /**
     * Test can_combine_with
     */
    public function test_can_combine_with()
    {
        $mc = new MultiCursor([], null);
        $sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->assertFalse($mc->can_combine_with($sc));
        $this->assertTrue($mc->can_combine_with(new MultiCursor([], null)));
    }

    /**
     * Test combine_with
     */
    public function test_combine_with()
    {
        $sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sc->expects($this->any())
            ->method('_can_combine_with')
            ->willReturn(true);
        $sc->expects($this->any())
            ->method('_combine_with')
            ->willReturn($sc);
        $mc = new MultiCursor([
            'Stream(123)' => $sc,
        ], null);
        $empty_mc = new MultiCursor([], null);

        $another_sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $another_mc = new MultiCursor(['Stream(123)' => $another_sc], null);

        $this->assertEquals($mc, $empty_mc->combine_with($mc));
        $this->assertEquals($mc, $mc->combine_with($another_mc));
    }

    /**
     * Test to_string
     */
    public function test_to_string()
    {
        $sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sc->expects($this->any())
            ->method('to_string')
            ->willReturn('mockCursor');
        $mc = new MultiCursor(['Stream(123)' => $sc], null);
        $this->assertSame('Multi(Stream(123):mockCursor)', (string) $mc);
    }

    /**
     * Test cursor_for_stream
     */
    public function test_cursor_for_stream()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['amazing_stream'])
            ->getMockForAbstractClass();
        $cursor = new MultiCursor([
            'amazing_stream' => 'amazing_cursor',
        ]);
        $this->assertSame($cursor->cursor_for_stream($stream), 'amazing_cursor');

        $another_cursor = new MultiCursor([
            'regular_stream' => 'regular_cursor',
        ]);
        $this->assertNull($another_cursor->cursor_for_stream($stream));
    }

    /**
     * Test is_empty
     */
    public function test_is_empty()
    {
        $cursor = new MultiCursor([
            'amazing_stream' => 'amazing_cursor',
        ]);
        $this->assertFalse($cursor->is_empty());

        $cursor = new MultiCursor([]);
        $this->assertTrue($cursor->is_empty());
    }

    /**
     * Test get_injector_state
     */
    public function test_get_injector_state()
    {
        $cursor = new MultiCursor([], [
            'offset' => 123,
        ]);
        $this->assertSame($cursor->get_injector_state(), [
            'offset' => 123,
        ]);
    }

    /**
     * Test with_injector_state
     */
    public function test_with_injector_state()
    {
        $cursor = new MultiCursor([], [
            'offset' => 111,
        ]);
        $state = [
            'offset' => 222,
        ];
        $this->assertSame($cursor->with_injector_state($state)->get_injector_state(), [
            'offset' => 222,
        ]);
    }

    /**
     * Test combine_from
     */
    public function test_combine_from()
    {
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $el */
        $el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', new MultiCursor([], ['foo' => 2])])->getMockForAbstractClass();

        $cursor = new MultiCursor([], ['bar' => 1]);
        $cursor = $cursor->combine_from($el);
        $this->assertSame($cursor->get_injector_state(), ['bar' => 1]); // Injector state will override
    }
}
