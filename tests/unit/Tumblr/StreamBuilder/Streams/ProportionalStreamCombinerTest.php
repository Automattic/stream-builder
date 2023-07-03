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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\FilteredStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\ProportionalStreamCombiner;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamWeight;

/**
 * Class ProportionalStreamcombinerTest
 */
class ProportionalStreamCombinerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor failure
     * @return void
     */
    public function test_constructor_failure()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new ProportionalStreamCombiner([1], 'cool_combiner');
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream1 */
        $stream1 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['amazing_stream1'])
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $stream1->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'AmazingStream1',
            ]);

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream2 */
        $stream2 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['amazing_stream2'])
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $stream2->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'AmazingStream2',
            ]);

        $sw1 = new StreamWeight(1, $stream1);
        $sw2 = new StreamWeight(2, $stream2);

        $combiner = new ProportionalStreamCombiner([$sw1, $sw2], 'amazing_combiner');

        $template = [
            '_type' => ProportionalStreamCombiner::class,
            'stream_weight_array' => [
                0 => [
                    '_type' => StreamWeight::class,
                    'weight' => 1.0,
                    'stream' => [
                        '_type' => 'AmazingStream1',
                    ],
                ],
                1 => [
                    '_type' => StreamWeight::class,
                    'weight' => 2.0,
                    'stream' => [
                        '_type' => 'AmazingStream2',
                    ],
                ],
            ],
        ];
        $this->assertSame($template, $combiner->to_template());
        return $combiner;
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => ProportionalStreamCombiner::class,
            'stream_weight_array' => [
                [
                    '_type' => StreamWeight::class,
                    'weight' => 10.0,
                    'stream' => [
                        '_type' => NullStream::class,
                    ],
                ],
                [
                    '_type' => StreamWeight::class,
                    'weight' => 1.0,
                    'stream' => [
                        '_type' => NullStream::class,
                    ],
                ],
            ],
        ];

        $context = new StreamContext($template, []);
        $combiner = ProportionalStreamCombiner::from_template($context);
        $this->assertSame($template, $combiner->to_template());
    }

    /**
     * Test combine
     * @return ProportionalStreamCombiner
     */
    public function test_combine()
    {
        $cursor = $this->getMockBuilder(StreamCursor::class)->disableOriginalConstructor()->getMockForAbstractClass();

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream1 */
        $stream1 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['amazing_stream1'])->getMockForAbstractClass();
        $el1 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['amazing_stream1', $cursor])->getMockForAbstractClass();

        $stream1->expects($this->exactly(0))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [
                $el1,
                $el1,
                $el1,
                $el1,
            ]));

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream2 */
        $stream2 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['amazing_stream2'])->getMockForAbstractClass();
        $el2 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['amazing_stream2', $cursor])->getMockForAbstractClass();

        $stream2->expects($this->exactly(5))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [
                $el2,
                $el2,
                $el2,
                $el2,
            ]));

        $sw1 = new StreamWeight(0.00001, $stream1);
        $sw2 = new StreamWeight(10000, $stream2);

        $combiner = new ProportionalStreamCombiner([$sw1, $sw2], 'amazing_combiner');

        $result = $combiner->enumerate(6);
        $this->assertFalse($result->is_exhaustive());
        $this->assertSame(6, $result->get_size());

        $result = $combiner->enumerate(10);
        $this->assertFalse($result->is_exhaustive());
        $this->assertSame(10, $result->get_size());

        return $combiner;
    }

    /**
     * Test combine with wrong cursor
     * @depends test_combine
     * @param ProportionalStreamCombiner $combiner The combiner
     */
    public function test_combine_with_wrong_cursor(ProportionalStreamCombiner $combiner)
    {
        $this->expectException(InappropriateCursorException::class);
        $combiner->enumerate(10, new FilteredStreamCursor());
    }
}
