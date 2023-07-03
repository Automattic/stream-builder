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

use Tumblr\StreamBuilder\Exceptions\UncombinableCursorException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\ConcatenatedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\MaxIntCursor;
use Tumblr\StreamBuilder\StreamCursors\SizeLimitedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;

/**
 * Class ConcatenatedStreamCursorTest
 */
class ConcatenatedStreamCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test get_source_index
     */
    public function test_get_source_index()
    {
        $cursor = new ConcatenatedStreamCursor(2);
        $this->assertSame($cursor->get_source_index(), 2);
    }

    /**
     * Test get_source_cursor
     */
    public function test_get_source_cursor()
    {
        $cursor = new ConcatenatedStreamCursor(2);
        $this->assertNull($cursor->get_source_cursor());

        $inner_cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $cursor = new ConcatenatedStreamCursor(2, $inner_cursor);
        $this->assertSame($cursor->get_source_cursor(), $inner_cursor);
    }

    /**
     * Test combine_with
     */
    public function test_combine_with()
    {
        $cursor = new ConcatenatedStreamCursor(1, new MaxIntCursor(1));
        $another_cursor = new ConcatenatedStreamCursor(2, new MaxIntCursor(2));
        $same_cursor = new ConcatenatedStreamCursor(1, new MaxIntCursor(2));
        $lower_cursor = new ConcatenatedStreamCursor(0, new MaxIntCursor(2));

        $this->assertSame($cursor, $cursor->combine_with(null));
        $this->assertSame($another_cursor, $cursor->combine_with($another_cursor));
        $this->assertSame($same_cursor->get_source_cursor(), $cursor->combine_with($same_cursor)->get_source_cursor());
        $this->assertSame($cursor, $cursor->combine_with($lower_cursor));
    }

    /**
     * Test combine_with with exception
     */
    public function test_combine_with_exception()
    {
        $cursor = new ConcatenatedStreamCursor(1, new MaxIntCursor(1));
        $another_cursor = new ConcatenatedStreamCursor(1, new SizeLimitedStreamCursor(null, 1));
        $this->expectException(UncombinableCursorException::class);
        $cursor->combine_with($another_cursor);
    }

    /**
     * Test to_string
     */
    public function test_to_string()
    {
        $cursor = new ConcatenatedStreamCursor(1);
        $another_cursor = new ConcatenatedStreamCursor(2, $cursor);

        $this->assertSame('Concat(2,Concat(1,))', (string) $another_cursor);
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $cursor = new ConcatenatedStreamCursor(1);
        $another_cursor = new ConcatenatedStreamCursor(2, $cursor);

        $template = [
            '_type' => ConcatenatedStreamCursor::class,
            'i' => 2,
            'c' => [
                '_type' => ConcatenatedStreamCursor::class,
                'i' => 1,
                'c' => null,
            ],
        ];
        $this->assertSame($template, $another_cursor->to_template());

        return $template;
    }

    /**
     * @depends test_to_template
     * @param array $template The cursor template
     */
    public function test_from_template(array $template)
    {
        $context = new StreamContext($template, []);
        $this->assertSame($template, ConcatenatedStreamCursor::from_template($context)->to_template());
    }

    /**
     * @return array[]
     */
    public function combineWithProvider(): array
    {
        $cursor = new ConcatenatedStreamCursor(1, new MaxIntCursor(1));
        return [
            [$cursor, null, true],
            [$cursor, new ConcatenatedStreamCursor(1, new MaxIntCursor(2)), true],
            [$cursor, new ConcatenatedStreamCursor(2, new MaxIntCursor(2)), true],
            [$cursor, new ConcatenatedStreamCursor(2, new SizeLimitedStreamCursor(null, 1)), true],
            [$cursor, new SizeLimitedStreamCursor(null, 1), false],
            [$cursor, new ConcatenatedStreamCursor(1, new SizeLimitedStreamCursor(null, 1)), false],
            [new ConcatenatedStreamCursor(1, null), $cursor, true],
        ];
    }

    /**
     * Test _can_combine_with function.
     * @dataProvider combineWithProvider
     * @param StreamCursor|null $cursor To be combined cursor.
     * @param StreamCursor|null $another_cursor To be combine cursor
     * @param bool $expected Expected result
     */
    public function testCanCombineWith(?StreamCursor $cursor, ?StreamCursor $another_cursor, bool $expected): void
    {
        $this->assertSame($expected, $cursor->can_combine_with($another_cursor));
    }
}
