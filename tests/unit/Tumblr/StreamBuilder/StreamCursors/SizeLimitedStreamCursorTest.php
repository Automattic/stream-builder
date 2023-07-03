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
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\SizeLimitedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;

/**
 * Class SizeLimitedStreamCursorTest
 */
class SizeLimitedStreamCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data Provider
     */
    public function combine_with_provider()
    {
        return [
            [5, 8, 9],
            [0, 0, 1],
            [0, 15, 16],
            [15, 0, 16],
        ];
    }

    /**
     * Test combine_with
     * @dataProvider combine_with_provider
     * @param int $size Cursor size.
     * @param int $another_size Another cursor size.
     * @param int $expected_size Expected size.
     */
    public function test_combine_with(int $size, int $another_size, int $expected_size)
    {
        $cursor = new SizeLimitedStreamCursor(null, $size);
        $another_cursor = new SizeLimitedStreamCursor(null, $another_size);

        /** @var SizeLimitedStreamCursor $combined */
        $combined = $cursor->combine_with($another_cursor);
        $this->assertSame($expected_size, $combined->get_current_size());
    }

    /**
     * Test constructor, while count input is negative.
     */
    public function test_constructor_failure()
    {
        $this->expectException(\InvalidArgumentException::class);
        new SizeLimitedStreamCursor(null, -1);
    }

    /**
     * Test to_string
     */
    public function test_to_string()
    {
        $this->assertSame('SizeLimited(,10)', (string) new SizeLimitedStreamCursor(null, 10));

        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $cursor */
        $cursor = $this->getMockBuilder(StreamCursor::class)->disableOriginalConstructor()->getMock();
        $cursor->expects($this->once())
            ->method('to_string')
            ->willReturn('amazing_inner_cursor');

        $this->assertSame('SizeLimited(amazing_inner_cursor,10)', (string) new SizeLimitedStreamCursor($cursor, 10));
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $cursor = new MultiCursor([]);
        $size_limited_cursor = new SizeLimitedStreamCursor($cursor, 10);

        $template = [
            '_type' => SizeLimitedStreamCursor::class,
            'c' => [
                '_type' => MultiCursor::class,
                's' => [],
                'i' => null,
            ],
            'ct' => 10,
        ];
        $this->assertSame($size_limited_cursor->to_template(), $template);
        return $template;
    }

    /**
     * Test from_template
     * @depends test_to_template
     * @param array $template Cursor template.
     */
    public function test_from_template(array $template)
    {
        $context = new StreamContext($template, []);
        $this->assertSame(SizeLimitedStreamCursor::from_template($context)->to_template(), $template);
    }
}
