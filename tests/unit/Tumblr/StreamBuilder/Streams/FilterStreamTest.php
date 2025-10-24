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
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\FilteredStream;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class FilterStreamTest
 *
 * @covers \Tumblr\StreamBuilder\Streams\FilteredStream
 */
class FilterStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test enumerate behavior.
     * @return void
     */
    public function test_enumerate_no_retry()
    {
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $el */
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $released_el = $el;

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, [$el, $el, $el, $el, $el]));

        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)->setConstructorArgs(['ello'])->getMock();
        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el, $el, $el], [$released_el, $released_el]));

        $stream = new FilteredStream($inner_stream, $filter, 'bar_foo', 0, 0);
        $result = $stream->enumerate(5);
        $this->assertSame(3, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test enumerate behavior.
     * @return void
     */
    public function test_enumerate_0_raw_batch()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, []));

        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)->setConstructorArgs(['ello'])->getMock();

        $stream = new FilteredStream($inner_stream, $filter, 'bar_foo', 0, 0);
        $result = $stream->enumerate(5);
        $this->assertSame(0, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test enumerate behavior.
     * @return void
     */
    public function test_enumerate_retry()
    {
        $retry_cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();

        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $el */
        $el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $retry_cursor])->getMock();
        $released_el = $el;

        $retry_cursor->expects($this->any())
            ->method('_can_combine_with')
            ->willReturn(true);

        $retry_cursor->expects($this->any())
            ->method('_combine_with')
            ->willReturn($retry_cursor);

        $inner_stream->expects($this->exactly(3))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el, $el, $el, $el, $el, $el]));

        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)->setConstructorArgs(['ello'])->getMock();
        $filter->expects($this->exactly(3))
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el, $el], [$released_el, $released_el, $released_el, $released_el]));

        $stream = new FilteredStream($inner_stream, $filter, 'bar_foo', 2, 0.2);
        $result = $stream->enumerate(5);
        $this->assertSame(5, $result->get_size());
        $this->assertFalse($result->is_exhaustive());
    }

    /**
     * Test enumerate with inappropriate cursor.
     */
    public function test_enumerate_exception()
    {
        $this->expectException(InappropriateCursorException::class);
        /** @var Stream $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)->setConstructorArgs(['ello'])->getMock();

        $stream = new FilteredStream($inner_stream, $filter, 'amazing_stream');
        $cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $cursor->expects($this->once())
            ->method('to_string')
            ->willReturn('amazing_inappropriate_cursor');

        $stream->enumerate(10, $cursor);
    }

    /**
     * Test enumerate when skip_filters is true
     * @return void
     */
    public function test_enumerate_skip_filters()
    {
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $el */
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $released_el = $el;

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el, $el, $el, $el, $el]));

        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)->setConstructorArgs(['ello'])->getMock();
        $filter->expects($this->never())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el, $el, $el], [$released_el, $released_el]));

        $stream = new FilteredStream($inner_stream, $filter, 'bar_foo', 0, 0, true);
        $result = $stream->enumerate(5);
        $this->assertSame(5, $result->get_size());
    }

    /**
     * Tests the is_exhaustive behavior when the final retry filters out all elements.
     *
     * When the last inner stream result is filtered to nothing, the result should
     * still be marked as exhaustive only if the inner stream declared it so.
     *
     * @dataProvider provide_final_retry_exhaustion_cases
     * @param bool $final_inner_exhausted Whether the final inner stream result is marked as exhaustive.
     * @param bool $expected_exhaustive The expected value of the is_exhaustive flag in the result.
     * /
     */
    public function test_is_exhaustive_when_final_retry_filters_all(bool $final_inner_exhausted, bool $expected_exhaustive)
    {
        $keep = new MockedPostRefElement(123, 234);
        $drop = new MockedPostRefElement(567, 899);

        $inner_stream = $this->createMock(Stream::class);
        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$keep]),
                new StreamResult($final_inner_exhausted, [$drop])
            );

        $filter = $this->createMock(StreamFilter::class);
        $filter->expects($this->exactly(2))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([$keep], []),
                new StreamFilterResult([], [])
            );

        $filtered_stream = new FilteredStream($inner_stream, $filter, 'test', 1, 0.0);
        $stream_result = $filtered_stream->enumerate(3);

        $this->assertSame(1, $stream_result->get_size());
        $this->assertSame($expected_exhaustive, $stream_result->is_exhaustive());

        $elements = $stream_result->get_elements();
        $this->assertCount(1, $elements);
        $this->assertSame($keep, $elements[0]->get_original_element());
    }

    /**
     * Data provider for test_is_exhaustive_when_final_retry_filters_all
     * @return iterable
     */
    public function provide_final_retry_exhaustion_cases(): iterable
    {
        yield 'inner_not_exhausted' => [false, false];
        yield 'inner_exhausted' => [true, true];
    }
}
