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

use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\ProportionalRoundRobinStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\ProportionalRoundRobinStream;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Tests for the ProportionalRoundRobinStream
 */
class ProportionalRoundRobinStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the constructor fails when no minor streams are provided
     * @return void
     */
    public function test_construct__no_minors()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [], [], 3, 2, 'foo');
    }

    /**
     * Test that the constructor fails when an invalid minor stream is provided
     * @return void
     */
    public function test_construct__bad_minor()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [ 'not-a-stream' ], [], 3, 2, 'foo');
    }

    /**
     * Test that the constructor fails when an empty minor stream order is provided
     * @return void
     */
    public function test_construct__no_minor_order()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [ $this->make_string_stream('n1', []) ], [], 3, 2, 'foo');
    }

    /**
     * Test that the constructor fails when bad minor stream order is provided
     * @return void
     */
    public function test_construct__invalid_minor_order__negative()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [ $this->make_string_stream('n1', []) ], [ -1 ], 3, 2, 'foo');
    }

    /**
     * Test that the constructor fails when bad minor stream order is provided
     * @return void
     */
    public function test_construct__invalid_minor_order__too_big()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [ $this->make_string_stream('n1', []) ], [ 1 ], 3, 2, 'foo');
    }

    /**
     * Test that the constructor fails when bad minor modulus is provided
     * @return void
     */
    public function test_construct__invalid_minor_modulus()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [ $this->make_string_stream('n1', []) ], [ 1 ], 1, 0, 'foo');
    }

    /**
     * Test that the constructor fails when bad minor remainder is provided
     * @return void
     */
    public function test_construct__invalid_minor_remainder__negative()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [ $this->make_string_stream('n1', []) ], [ 1 ], 3, -1, 'foo');
    }

    /**
     * Test that the constructor fails when bad minor remainder is provided
     * @return void
     */
    public function test_construct__invalid_minor_remainder__too_big()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalRoundRobinStream($this->make_string_stream('m', []), [ $this->make_string_stream('n1', []) ], [ 1 ], 3, 3, 'foo');
    }

    /**
     * Test enumeration with a single minor at 1 mod 3
     * @return void
     */
    public function test_enumerate_single_minor_1_3()
    {
        $major = [ 'm0', 'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9' ];
        $minor_0 = [ 'n0a', 'n0b', 'n0c', 'n0d' ];
        $s = new ProportionalRoundRobinStream(
            $this->make_string_stream('m', $major),
            [ $this->make_string_stream('n0', $minor_0) ],
            [ 0 ],
            3,
            1,
            'foo'
        );

        $r1 = $s->enumerate(5);
        $this->assertSame([
            'StringElem(ArrayMax(0),m0)',
            'StringElem(ArrayMax(0),n0a)',
            'StringElem(ArrayMax(1),m1)',
            'StringElem(ArrayMax(2),m2)',
            'StringElem(ArrayMax(1),n0b)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r1->get_elements()));
        $c1 = $r1->get_combined_cursor();
        $this->assertSame('PropRobin(5,ArrayMax(2),[0:ArrayMax(1)])', (string) $c1);
        $this->assertFalse($r1->is_exhaustive());

        $r2 = $s->enumerate(8, $c1);
        $this->assertSame([
            'StringElem(ArrayMax(3),m3)',
            'StringElem(ArrayMax(4),m4)',
            'StringElem(ArrayMax(2),n0c)',
            'StringElem(ArrayMax(5),m5)',
            'StringElem(ArrayMax(6),m6)',
            'StringElem(ArrayMax(3),n0d)',
            'StringElem(ArrayMax(7),m7)',
            'StringElem(ArrayMax(8),m8)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r2->get_elements()));
        $c2 = $r2->get_combined_cursor();
        $this->assertSame('PropRobin(13,ArrayMax(8),[0:ArrayMax(3)])', (string) $c2);
        $this->assertFalse($r2->is_exhaustive());

        $r3 = $s->enumerate(11, $c2);
        $this->assertSame([
            'StringElem(ArrayMax(9),m9)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r3->get_elements()));
        $c3 = $r3->get_combined_cursor();
        $this->assertSame('PropRobin(14,ArrayMax(9),[0:ArrayMax(3)])', (string) $c3);
        $this->assertTrue($r3->is_exhaustive());
    }


    /**
     * Test enumeration with three minors at 0 mod 3, order 0 1 0 2
     * @return void
     */
    public function test_enumerate_triple_minor_0_4_alternate()
    {
        $major = array_map(function ($i) {
            return sprintf('m%d', $i);
        }, range(0, 20));
        $minor_0 = [ 'n0a', 'n0b', 'n0c', 'n0d', 'n0e', 'n0f', 'n0g', 'n0h' ];
        $minor_1 = [ 'n1a', 'n1b', 'n1c', 'n1d' ];
        $minor_2 = [ 'n2a', 'n2b' ];
        $s = new ProportionalRoundRobinStream(
            $this->make_string_stream('m', $major),
            [
                $this->make_string_stream('n0', $minor_0),
                $this->make_string_stream('n1', $minor_1),
                $this->make_string_stream('n2', $minor_2),
            ],
            [ 0, 1, 0, 2 ],
            3,
            0,
            'foo'
        );

        $r1 = $s->enumerate(11);
        $this->assertSame([
            'StringElem(ArrayMax(0),n0a)',
            'StringElem(ArrayMax(0),m0)',
            'StringElem(ArrayMax(1),m1)',
            'StringElem(ArrayMax(0),n1a)',
            'StringElem(ArrayMax(2),m2)',
            'StringElem(ArrayMax(3),m3)',
            'StringElem(ArrayMax(1),n0b)',
            'StringElem(ArrayMax(4),m4)',
            'StringElem(ArrayMax(5),m5)',
            'StringElem(ArrayMax(0),n2a)',
            'StringElem(ArrayMax(6),m6)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r1->get_elements()));
        $c1 = $r1->get_combined_cursor();
        $this->assertSame('PropRobin(11,ArrayMax(6),[0:ArrayMax(1),1:ArrayMax(0),2:ArrayMax(0)])', (string) $c1);
        $this->assertFalse($r1->is_exhaustive());

        $r2 = $s->enumerate(9, $c1);
        $this->assertSame([
            'StringElem(ArrayMax(7),m7)',
            'StringElem(ArrayMax(2),n0c)',
            'StringElem(ArrayMax(8),m8)',
            'StringElem(ArrayMax(9),m9)',
            'StringElem(ArrayMax(1),n1b)',
            'StringElem(ArrayMax(10),m10)',
            'StringElem(ArrayMax(11),m11)',
            'StringElem(ArrayMax(3),n0d)',
            'StringElem(ArrayMax(12),m12)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r2->get_elements()));
        $c2 = $r2->get_combined_cursor();
        $this->assertSame('PropRobin(20,ArrayMax(12),[0:ArrayMax(3),1:ArrayMax(1),2:ArrayMax(0)])', (string) $c2);
        $this->assertFalse($r2->is_exhaustive());

        $r3 = $s->enumerate(16, $c2);
        $this->assertSame([
            'StringElem(ArrayMax(13),m13)',
            'StringElem(ArrayMax(1),n2b)',
            'StringElem(ArrayMax(14),m14)',
            'StringElem(ArrayMax(15),m15)',
            'StringElem(ArrayMax(4),n0e)',
            'StringElem(ArrayMax(16),m16)',
            'StringElem(ArrayMax(17),m17)',
            'StringElem(ArrayMax(2),n1c)',
            'StringElem(ArrayMax(18),m18)',
            'StringElem(ArrayMax(19),m19)',
            'StringElem(ArrayMax(5),n0f)',
            'StringElem(ArrayMax(20),m20)',

        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r3->get_elements()));
        $c3 = $r3->get_combined_cursor();
        $this->assertSame('PropRobin(32,ArrayMax(20),[0:ArrayMax(5),1:ArrayMax(2),2:ArrayMax(1)])', (string) $c3);
        $this->assertTrue($r3->is_exhaustive());
    }

    /**
     * Test enumeration with a two minors, used in reverse order, at 3 mod 4. The enumeration amounts used trigger
     * an edge case that will generate gaps in the minor cursor array.
     * @return void
     */
    public function test_enumerate_double_minor_3_4_gaps()
    {
        $major = [ 'm0', 'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9' ];
        $minor_0 = [ 'n0a', 'n0b' ];
        $minor_1 = [ 'n1a', 'n1b' ];
        $s = new ProportionalRoundRobinStream(
            $this->make_string_stream('m', $major),
            [
                $this->make_string_stream('n0', $minor_0),
                $this->make_string_stream('n1', $minor_1),
            ],
            [ 1, 0 ],
            4,
            3,
            'foo'
        );

        $r1 = $s->enumerate(2);
        $this->assertSame([
            'StringElem(ArrayMax(0),m0)',
            'StringElem(ArrayMax(1),m1)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r1->get_elements()));
        $c1 = $r1->get_combined_cursor();
        $this->assertSame([
            '_type' => ProportionalRoundRobinStreamCursor::class,
            'i' => 2,
            'm' => [ 'i' => 1 ],
            'n' => [],
        ], $c1->to_template());
        $this->assertSame('PropRobin(2,ArrayMax(1),[])', (string) $c1);
        $this->assertFalse($r1->is_exhaustive());

        $r2 = $s->enumerate(4, $c1);
        $this->assertSame([
            'StringElem(ArrayMax(2),m2)',
            'StringElem(ArrayMax(0),n1a)',
            'StringElem(ArrayMax(3),m3)',
            'StringElem(ArrayMax(4),m4)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r2->get_elements()));
        $c2 = $r2->get_combined_cursor();
        $this->assertSame([
            '_type' => ProportionalRoundRobinStreamCursor::class,
            'i' => 6,
            'm' => [ 'i' => 4 ],
            'n' => [
                1 => [ 'i' => 0 ],
            ],
        ], $c2->to_template());
        $this->assertSame('PropRobin(6,ArrayMax(4),[1:ArrayMax(0)])', (string) $c2);
        $this->assertFalse($r2->is_exhaustive());

        $r3 = $s->enumerate(4, $c2);
        $this->assertSame([
            'StringElem(ArrayMax(5),m5)',
            'StringElem(ArrayMax(0),n0a)',
            'StringElem(ArrayMax(6),m6)',
            'StringElem(ArrayMax(7),m7)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r3->get_elements()));
        $c3 = $r3->get_combined_cursor();
        $this->assertSame([
            '_type' => ProportionalRoundRobinStreamCursor::class,
            'i' => 10,
            'm' => [ 'i' => 7 ],
            'n' => [
                0 => [ 'i' => 0 ],
                1 => [ 'i' => 0 ],
            ],
        ], $c3->to_template());
        $this->assertSame('PropRobin(10,ArrayMax(7),[0:ArrayMax(0),1:ArrayMax(0)])', (string) $c3);
        $this->assertFalse($r3->is_exhaustive());

        $r4 = $s->enumerate(4, $c3);
        $this->assertSame([
            'StringElem(ArrayMax(8),m8)',
            'StringElem(ArrayMax(1),n1b)',
            'StringElem(ArrayMax(9),m9)',
        ], array_map(function (StreamElement $e) {
            return ((string) $e);
        }, $r4->get_elements()));
        $c4 = $r4->get_combined_cursor();
        $this->assertSame([
            '_type' => ProportionalRoundRobinStreamCursor::class,
            'i' => 13,
            'm' => [ 'i' => 9 ],
            'n' => [
                0 => [ 'i' => 0 ],
                1 => [ 'i' => 1 ],
            ],
        ], $c4->to_template());
        $this->assertSame('PropRobin(13,ArrayMax(9),[0:ArrayMax(0),1:ArrayMax(1)])', (string) $c4);
        $this->assertTrue($r4->is_exhaustive());
    }

    /**
     * Make a stub stream for testing.
     * @param string $id The id of the stream.
     * @param string[] $strings The strings in the stream.
     * @return Stream
     */
    public function make_string_stream(string $id, array $strings)
    {
        return new class($strings, $id) extends Stream {
            /** @var StreamElement[] */
            private $elements;

            /**
             * @param string[] $strings The strings in the stream.
             * @param string $id The identity of the stream.
             */
            public function __construct(array $strings, string $id)
            {
                parent::__construct($id);
                $elements = [];
                foreach ($strings as $idx => $string) {
                    $cur = new class($idx) extends StreamCursor {
                        /** @var int */
                        public $index;

                        /**
                         * @param int $index The index of this item in the strings array.
                         */
                        public function __construct(int $index)
                        {
                            $this->index = $index;
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
                            return new self(max($this->index, $other->index));
                        }

                        /**
                         * @inheritDoc
                         */
                        protected function to_string(): string
                        {
                            return sprintf('ArrayMax(%d)', $this->index);
                        }

                        /**
                         * @inheritDoc
                         */
                        public function to_template(): array
                        {
                            return [ 'i' => $this->index ];
                        }

                        /**
                         * @inheritDoc
                         */
                        public static function from_template(StreamContext $context): ?self
                        {
                            return null;
                        }
                    };
                    $elements[$idx] = new class($string, $id, $cur) extends LeafStreamElement {
                        /** @var string */
                        private $value;

                        /**
                         * @inheritDoc
                         */
                        public function __construct(string $value, string $provider_identity, StreamCursor $cursor)
                        {
                            parent::__construct($provider_identity, $cursor);
                            $this->value = $value;
                        }
                        /**
                         * @inheritDoc
                         */
                        public function get_cache_key()
                        {
                            return null;
                        }

                        /**
                         * @inheritDoc
                         */
                        protected function to_string(): string
                        {
                            return sprintf('StringElem(%s,%s)', $this->get_cursor(), $this->value);
                        }

                        /**
                         * @inheritDoc
                         */
                        public function to_template(): array
                        {
                            // Testing Shim
                            return [];
                        }

                        /**
                         * @inheritDoc
                         */
                        public static function from_template(StreamContext $context): ?self
                        {
                            // Testing Shim
                            return null;
                        }
                    };
                }
                $this->elements = $elements;
            }

            /**
             * @inheritDoc
             */
            protected function _enumerate(
                int $count,
                StreamCursor $cursor = null,
                StreamTracer $tracer = null,
                ?EnumerationOptions $option = null
            ): StreamResult {
                $slice = array_slice($this->elements, is_null($cursor) ? 0 : $cursor->index + 1, $count);
                return new StreamResult(count($slice) < $count, $slice);
            }

            /**
             * @inheritDoc
             */
            public function to_template(): array
            {
                return [];
            }

            /**
             * @inheritDoc
             */
            public static function from_template(StreamContext $context): ?self
            {
                return null;
            }
        };
    }

    /**
     * Test when minor stream is exhausted, we should not exhaust main stream.
     */
    public function testMinorExhaust(): void
    {
        $element = new MockedPostRefElement(1, 1);
        $minor = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $minor->method('_enumerate')
            ->willReturn(new StreamResult(true, []));
        $main = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $main->method('_enumerate')
            ->willReturn(new StreamResult(false, array_fill(0, 20, $element)));
        $proportional_stream = new ProportionalRoundRobinStream($main, [$minor], [0], 2, 1, 'what');
        $result1 = $proportional_stream->enumerate(10);
        $this->assertFalse($result1->is_exhaustive());
        $result2 = $proportional_stream->enumerate(10);
        $this->assertFalse($result2->is_exhaustive());
    }

    /**
     * Test when minor stream throw exception, we should keep enumerate main stream.
     */
    public function testMinorException(): void
    {
        $element = new MockedPostRefElement(1, 1);
        $minor = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $minor->method('_enumerate')
            ->willThrowException(new \InvalidArgumentException());
        $main = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $main->method('_enumerate')
            ->willReturn(new StreamResult(false, array_fill(0, 20, $element)));
        $proportional_stream = new ProportionalRoundRobinStream($main, [$minor], [0], 2, 1, 'what');
        $result1 = $proportional_stream->enumerate(10);
        $this->assertFalse($result1->is_exhaustive());
        $result2 = $proportional_stream->enumerate(10);
        $this->assertFalse($result2->is_exhaustive());
    }
}
