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
use Tumblr\StreamBuilder\StreamCursors\InjectedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;

/**
 * Class InjectedStreamCursorTest
 */
class InjectedStreamCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test get_inner_cursor
     */
    public function test_get_inner_cursor()
    {
        $inner_cursor = $this->getMockBuilder(StreamCursor::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $cursor = new InjectedStreamCursor($inner_cursor);

        $this->assertSame($inner_cursor, $cursor->get_inner_cursor());
    }

    /**
     * Test get_injector_state
     */
    public function test_get_injector_state()
    {
        $state = [
            'offset' => 100,
        ];
        $cursor = new InjectedStreamCursor(null, $state);

        $this->assertSame($state, $cursor->get_injector_state());
    }

    /**
     * Test to_string
     */
    public function test_to_string()
    {
        $inner_cursor = $this->getMockBuilder(StreamCursor::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $inner_cursor->expects($this->once())
            ->method('to_string')
            ->willReturn('amazing_cursor');

        $state = [
            'offset' => 100,
        ];

        $cursor = new InjectedStreamCursor($inner_cursor, $state);
        $this->assertSame('InjectedStreamCursor(amazing_cursor)', (string) $cursor);
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $inner_cursor = new InjectedStreamCursor();
        $state = [
            'offset' => 100,
        ];

        $cursor = new InjectedStreamCursor($inner_cursor, $state);
        $this->assertSame([
            '_type' => InjectedStreamCursor::class,
            'c' => [
                '_type' => InjectedStreamCursor::class,
                'c' => null,
                'i' => null,
            ],
            'i' => [
                'offset' => 100,
            ],
        ], $cursor->to_template());
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => InjectedStreamCursor::class,
            'c' => [
                '_type' => InjectedStreamCursor::class,
                'c' => null,
                'i' => null,
            ],
            'i' => [
                'offset' => 100,
            ],
        ];

        $cursor = InjectedStreamCursor::from_template(new StreamContext($template, []));
        $this->assertSame($template, $cursor->to_template());
    }

    /**
     * Test can_combine_with
     */
    public function test_can_combine_with()
    {
        $inner_cursor = new InjectedStreamCursor();
        $empty = new InjectedStreamCursor();
        $non_empty = new InjectedStreamCursor($inner_cursor);

        $this->assertTrue($empty->can_combine_with(null));
        $this->assertTrue($empty->can_combine_with($non_empty));
        $this->assertTrue($non_empty->can_combine_with($non_empty));
    }
}
