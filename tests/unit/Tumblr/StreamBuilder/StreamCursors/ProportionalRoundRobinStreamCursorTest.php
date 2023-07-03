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
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamCursors\ProportionalRoundRobinStreamCursor;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * Tests for the ProportionalRoundRobinStreamCursor
 */
class ProportionalRoundRobinStreamCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the create_empty method is sane.
     * @return void
     */
    public function test_create_empty()
    {
        $cur = ProportionalRoundRobinStreamCursor::make_empty();
        $this->assertSame(0, $cur->get_begin_index());
        $this->assertNull($cur->get_major_stream_cursor());
        $this->assertEmpty($cur->get_minor_stream_cursors());
    }

    /**
     * Test constructing a cursor containing no minor cursors.
     * @return void
     */
    public function test_construct__no_minor()
    {
        $major = $this->mkmin(1000);
        $cur = new ProportionalRoundRobinStreamCursor(10, $major, []);
        $this->assertSame(10, $cur->get_begin_index());
        $this->assertSame($major, $cur->get_major_stream_cursor());
        $this->assertEmpty($cur->get_minor_stream_cursors());
    }

    /**
     * Test constructing a cursor containing two minor cursors.
     * @return void
     */
    public function test_construct__with_minors()
    {
        $major = $this->mkmin(1000);
        $minor_0 = $this->mkmin(500);
        $minor_1 = $this->mkmin(123456);
        $cur = new ProportionalRoundRobinStreamCursor(10, $major, [ $minor_0, $minor_1 ]);
        $this->assertSame(10, $cur->get_begin_index());
        $this->assertSame($major, $cur->get_major_stream_cursor());
        $this->assertSame($minor_0, $cur->get_minor_stream_cursor(0));
        $this->assertSame($minor_1, $cur->get_minor_stream_cursor(1));
        $this->assertNull($cur->get_minor_stream_cursor(2));
    }

    /**
     * Test constructing an invalid cursor.
     * @return void
     */
    public function test_construct__invalid_minor()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new ProportionalRoundRobinStreamCursor(10, $this->mkmin(1000), [ "not-a-stream-cursor" ]);
    }

    /**
     * Test combining cursors having minor curspr parity
     * @return void
     */
    public function test_combine__full()
    {
        $a_major = $this->mkmin(100);
        $a_minor_0 = $this->mkmin(7000);
        $a_minor_1 = $this->mkmin(123456);
        $a_cur = new ProportionalRoundRobinStreamCursor(10, $a_major, [ $a_minor_0, $a_minor_1 ]);

        $b_major = $this->mkmin(21);
        $b_minor_0 = $this->mkmin(7915);
        $b_minor_1 = $this->mkmin(123000);
        $b_cur = new ProportionalRoundRobinStreamCursor(21, $b_major, [ $b_minor_0, $b_minor_1 ]);

        $expected_combined = new ProportionalRoundRobinStreamCursor(21, $b_major, [ $a_minor_0, $b_minor_1 ]);
        $this->assertSame($expected_combined->to_template(), $a_cur->combine_with($b_cur)->to_template());
        $this->assertSame($expected_combined->to_template(), $b_cur->combine_with($a_cur)->to_template());
    }

    /**
     * Test combining cursors having minor-cursor imparity
     * @return void
     */
    public function test_combine__partial()
    {
        $a_major = $this->mkmin(100);
        $a_minor_1 = $this->mkmin(123456);
        $a_cur = new ProportionalRoundRobinStreamCursor(10, $a_major, [ 1 => $a_minor_1 ]);

        $b_major = $this->mkmin(21);
        $b_minor_0 = $this->mkmin(7915);
        $b_minor_1 = $this->mkmin(129000);
        $b_cur = new ProportionalRoundRobinStreamCursor(21, $b_major, [ $b_minor_0, $b_minor_1 ]);

        $expected_combined = new ProportionalRoundRobinStreamCursor(21, $b_major, [ $b_minor_0, $a_minor_1 ]);
        $this->assertSame($expected_combined->to_template(), $a_cur->combine_with($b_cur)->to_template());
        $this->assertSame($expected_combined->to_template(), $b_cur->combine_with($a_cur)->to_template());
    }

    /**
     * Test that the to_template method works as expected
     * @return void
     */
    public function test_to_template()
    {
        $anon_cur_class = get_class($this->mkmin(0));
        $cur = new ProportionalRoundRobinStreamCursor(
            10,
            $this->mkmin(1000),
            [$this->mkmin(500), $this->mkmin(123456)]
        );
        $template = $cur->to_template();
        $this->assertSame([
            '_type' => ProportionalRoundRobinStreamCursor::class,
            'i' => 10,
            'm' => [
                '_type' => $anon_cur_class,
                'v' => 1000,
            ],
            'n' => [
                [
                    '_type' => $anon_cur_class,
                    'v' => 500,
                ], [
                    '_type' => $anon_cur_class,
                    'v' => 123456,
                ],
            ],
        ], $template);
    }

    /**
     * Data provider for from_template
     * @return array
     */
    public function from_template_provider(): array
    {
        $anon_cur_class = get_class($this->mkmin(0));

        return [
            [
                [
                    '_type' => ProportionalRoundRobinStreamCursor::class,
                    'i' => 100,
                    'm' => ['v' => 321, '_type' => $anon_cur_class],
                    'n' => [['v' => 456, '_type' => $anon_cur_class], ['v' => 54321, '_type' => $anon_cur_class]],
                ],
                new ProportionalRoundRobinStreamCursor(100, $this->mkmin(321), [$this->mkmin(456), $this->mkmin(54321)]),
            ],
            [
                [
                    '_type' => ProportionalRoundRobinStreamCursor::class,
                    'i' => 100,
                    'n' => [['v' => 456, '_type' => $anon_cur_class], ['v' => 54321, '_type' => $anon_cur_class]],
                ],
                new ProportionalRoundRobinStreamCursor(100, null, [$this->mkmin(456), $this->mkmin(54321)]),
            ], // $major cursor can be null.
            [
                [
                    '_type' => ProportionalRoundRobinStreamCursor::class,
                    'i' => 100,
                    'm' => ['v' => 321, '_type' => $anon_cur_class],
                    'n' => [],
                ],
                new ProportionalRoundRobinStreamCursor(100, $this->mkmin(321), []),
            ], // $minor cursors can be empty.
        ];
    }

    /**
     * Test that the from_template method works as expected
     * @param array $template The template to deserialize from.
     * @param ProportionalRoundRobinStreamCursor $expected_cur The expected cursor.
     * @dataProvider from_template_provider
     * @return void
     */
    public function test_from_template(array $template, ProportionalRoundRobinStreamCursor $expected_cur)
    {
        $cur = StreamSerializer::from_template(new StreamContext($template, []));
        $this->assertSame($expected_cur->to_template(), $cur->to_template());
    }

    /**
     * Utility function to create a simple cursor that uses min as a combining function.
     * @param int $cur_value Te value of the cursor to create.
     * @return StreamCursor
     */
    private function mkmin(int $cur_value): StreamCursor
    {
        return new class($cur_value) extends StreamCursor {
            /** @var int */
            private $value;

            /**
             * @param int $value The value of the cursor
             */
            public function __construct(int $value)
            {
                $this->value = $value;
            }

            /**
             * @inheritDoc
             */
            protected function _can_combine_with(StreamCursor $other): bool
            {
                return true;
            }

            /**
             * @inheritDoc
             */
            protected function _combine_with(StreamCursor $other): StreamCursor
            {
                /** @var self $other */
                return new self(min($this->value, $other->value));
            }

            /**
             * @inheritDoc
             */
            protected function to_string(): string
            {
                return sprintf('MinTest(%d)', $this->value);
            }

            /**
             * @inheritDoc
             */
            public function to_template(): array
            {
                return [ '_type' => self::class, 'v' => $this->value ];
            }

            /**
             * @inheritDoc
             */
            public static function from_template(StreamContext $context): self
            {
                $template = $context->get_template();
                return new self(intval($template['v']));
            }
        };
    }
}
