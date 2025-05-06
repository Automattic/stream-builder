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

use Tests\mock\tumblr\StreamBuilder\StreamElements\MockLeafStreamElement;
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
     * Test that if elements are retained in an early attempt but all are filtered in the final retry,
     * the result is not marked as exhausted since some progress was made earlier and we may be able to fetch more.
     * @return void
     */
    public function test_enumerate_retains_then_filters_all_but_marks_exhausted()
    {
        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $cursor */
        $cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $cursor->method('_can_combine_with')->willReturn(true);
        $cursor->method('_combine_with')->willReturn($cursor);

        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $retained_el */
        $retained_el =  new MockLeafStreamElement("", $cursor);
        $filtered_el = new MockLeafStreamElement("", $cursor);

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$retained_el]),
                new StreamResult(false, [$filtered_el])
            );

        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)->setConstructorArgs(['test'])->getMock();
        $filter->expects($this->exactly(2))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([$retained_el], []), // 1 retained
                new StreamFilterResult([], [])              // all filtered out
            );

        $stream = new FilteredStream($inner_stream, $filter, 'test_identity', 1, 0.0);
        $result = $stream->enumerate(2); // ask for more than we will actually retain

        $this->assertSame(1, $result->get_size());

        $this->assertFalse(
            $result->is_exhaustive(),
            'FilteredStream should not be marked as exhausted if earlier recursion retained elements but final retry filtered out everything'
        );
    }

    /**
     * Test that when inner stream returns elements but the filter removes all of them,
     * and the inner stream is exhausted, the result is marked as exhaustive.
     * @return void
     */
    public function test_filters_all_and_inner_exhausted_marks_exhausted()
    {
        $cursor = $this->createMock(StreamCursor::class);
        $el = new MockLeafStreamElement("", $cursor);

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, [$el]));

        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([], []));

        $stream = new FilteredStream($inner_stream, $filter, 'test', 0, 0.0);
        $result = $stream->enumerate(3);

        $this->assertSame(0, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test that if we retain exactly want_count and inner stream is exhausted, result is marked as exhaustive.
     * @return void
     */
    public function test_retains_exactly_enough_and_inner_exhausted()
    {
        $cursor1 = $this->createMock(StreamCursor::class);
        $cursor2 = $this->createMock(StreamCursor::class);

        $cursor1->method('_can_combine_with')->willReturn(true);
        $cursor1->method('_combine_with')->willReturn($cursor1);
        $cursor2->method('_can_combine_with')->willReturn(true);
        $cursor2->method('_combine_with')->willReturn($cursor2);

        $el1 = new MockLeafStreamElement("", $cursor1);
        $el2 = new MockLeafStreamElement("", $cursor2);

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, [$el1, $el2]));

        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el1, $el2], []));

        $stream = new FilteredStream($inner_stream, $filter, 'test', 0, 0.0);
        $result = $stream->enumerate(2);

        $this->assertSame(2, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test that if we retain fewer than requested and inner stream is exhausted, result is still marked as exhaustive.
     * @return void
     */
    public function test_retains_fewer_than_requested_and_inner_exhausted()
    {
        $cursor = $this->createMock(StreamCursor::class);
        $el = new MockLeafStreamElement("", $cursor);

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, [$el]));

        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el], []));

        $stream = new FilteredStream($inner_stream, $filter, 'test', 0, 0.0);
        $result = $stream->enumerate(3);

        $this->assertSame(1, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test that if we retain more than requested and slice, even if inner is exhausted, result is not exhaustive.
     * @return void
     */
    public function test_retains_more_than_requested_and_sliced_inner_exhausted()
    {
        $cursor1 = $this->createMock(StreamCursor::class);
        $cursor2 = $this->createMock(StreamCursor::class);
        $cursor3 = $this->createMock(StreamCursor::class);
        $cursor4 = $this->createMock(StreamCursor::class);

        $cursor1->method('_can_combine_with')->willReturn(true);
        $cursor1->method('_combine_with')->willReturn($cursor1);
        $cursor2->method('_can_combine_with')->willReturn(true);
        $cursor2->method('_combine_with')->willReturn($cursor2);
        $cursor3->method('_can_combine_with')->willReturn(true);
        $cursor3->method('_combine_with')->willReturn($cursor3);
        $cursor4->method('_can_combine_with')->willReturn(true);
        $cursor4->method('_combine_with')->willReturn($cursor4);

        $el1 = new MockLeafStreamElement("", $cursor1);
        $el2 = new MockLeafStreamElement( "", $cursor2);
        $el3 = new MockLeafStreamElement( "", $cursor3);
        $el4 = new MockLeafStreamElement("", $cursor4);

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, [$el1, $el2, $el3, $el4]));

        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el1, $el2, $el3, $el4], []));

        $stream = new FilteredStream($inner_stream, $filter, 'test', 0, 0.0);
        $stream->slice_result = true;
        $result = $stream->enumerate(2);

        $this->assertSame(2, $result->get_size());
        $this->assertFalse($result->is_exhaustive());
    }

    /**
     * Test that overfetching with high ratio still returns exhaustive when all retained and inner exhausted.
     * @return void
     */
    public function test_overfetch_retains_all_and_inner_exhausted()
    {
        // Create distinct cursors for each element
        $cursors = [];
        $elements = [];
        for ($i = 1; $i < 7; $i++) {
            $cursor = $this->createMock(StreamCursor::class);
            $cursor->method('_can_combine_with')->willReturn(true);
            $cursor->method('_combine_with')->willReturn($cursor);

            $elements[] = new MockLeafStreamElement("$i", $cursor);
        }

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, $elements));

        // Retain 5 out of 6 to simulate overfetch with slice
        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult(array_slice($elements, 0, 5), []));

        $stream = new FilteredStream($inner_stream, $filter, 'test', 0, 1.0);
        $stream->slice_result = true;
        $result = $stream->enumerate(5);

        $this->assertSame(5, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test that cursors are combined across recursive calls and returned with result.
     * Ensures that even after multiple retries, the final result includes a valid combined cursor
     * and is marked as exhaustive when the final inner stream is exhausted and nothing is sliced.
     * @return void
     */
    public function test_combines_cursor_after_multiple_recursions()
    {
        // Create a shared combined cursor that all elements will resolve to
        $combined_cursor = $this->createMock(StreamCursor::class);
        $combined_cursor->method('_can_combine_with')->willReturn(true);
        $combined_cursor->method('_combine_with')->willReturn($combined_cursor);

        // First cursor (for first batch)
        $cursor1 = $this->createMock(StreamCursor::class);
        $cursor1->method('_can_combine_with')->willReturn(true);
        $cursor1->method('_combine_with')->willReturn($combined_cursor);

        // Second cursor (for second batch)
        $cursor2 = $this->createMock(StreamCursor::class);
        $cursor2->method('_can_combine_with')->willReturn(true);
        $cursor2->method('_combine_with')->willReturn($combined_cursor);

        // Create PostSearchStreamElements with distinct cursors
        $el1 = new MockLeafStreamElement( "", $cursor1);
        $el2 = new MockLeafStreamElement("", $cursor2);

        // Inner stream: returns 1 element per call
        $inner_stream = $this->createMock(Stream::class);
        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$el1]), // not exhaustive, forces retry
                new StreamResult(true, [$el2])   // final call, is_exhaustive
            );

        // Filter passes both through, one per call
        $filter = $this->createMock(StreamFilter::class);
        $filter->expects($this->exactly(2))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([$el1], []),
                new StreamFilterResult([$el2], [])
            );

        // Create and run stream
        $stream = new FilteredStream($inner_stream, $filter, 'test_identity', 2, 0.0);
        $stream->slice_result = false;

        $result = $stream->enumerate(2);

        // Diagnostics
        $this->assertSame(2, $result->get_size(), 'Expected two elements to be retained.');
        $this->assertTrue($result->is_exhaustive(), 'Expected stream to be marked as exhausted.');
        $this->assertNotNull($result->get_combined_cursor(), 'Expected combined cursor to be present.');
        $this->assertInstanceOf(StreamCursor::class, $result->get_combined_cursor());
        $this->assertNotNull($result->get_combined_cursor());
    }

    /**
     * Test that filtering keeps retrying until retry depth is hit and returns partial result.
     * @return void
     */
    public function test_retries_until_depth_exhausted_then_returns_partial()
    {
        $cursor = $this->createMock(StreamCursor::class);
        $cursor->method('_can_combine_with')->willReturn(true);
        $cursor->method('_combine_with')->willReturn($cursor);

        $el = new MockLeafStreamElement( "", $cursor);

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        $inner_stream->expects($this->exactly(3))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el, $el, $el]));

        $filter->expects($this->exactly(3))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([$el], []),
                new StreamFilterResult([], []),
                new StreamFilterResult([], [])
            );

        $stream = new FilteredStream($inner_stream, $filter, 'test', 2, 0.0);
        $result = $stream->enumerate(5);

        $this->assertSame(1, $result->get_size());
        $this->assertFalse($result->is_exhaustive());
    }

    public function test_retained_some_final_retry_filters_all_and_inner_exhausted_marks_exhausted()
    {
        // Create mock cursor that can be reused
        $cursor = $this->createMock(StreamCursor::class);
        $cursor->method('_can_combine_with')->willReturn(true);
        $cursor->method('_combine_with')->willReturn($cursor);

        // Retained element from first recursion
        $retained_el = new MockLeafStreamElement("", $cursor);

        // Filtered-out element in final retry
        $filtered_el = new MockLeafStreamElement("", $cursor);

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        // First call retains one element
        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$retained_el]),
                new StreamResult(true, [$filtered_el]) // Final retry, inner exhausted
            );

        $filter->expects($this->exactly(2))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([$retained_el], []),
                new StreamFilterResult([], []) // Final retry filters all
            );

        $stream = new FilteredStream($inner_stream, $filter, 'test', 1, 0.0);

        $result = $stream->enumerate(3);

        $this->assertSame(1, $result->get_size(), 'Expected one retained element');
        $this->assertTrue($result->is_exhaustive(), 'Expected stream to be marked as exhausted since inner was exhausted');
    }

    public function test_final_retry_retains_more_than_remaining_and_is_sliced()
    {
        $want_count = 3;

        $cursor1 = $this->createMock(StreamCursor::class);
        $cursor2 = $this->createMock(StreamCursor::class);
        $cursor3 = $this->createMock(StreamCursor::class);
        $cursor4 = $this->createMock(StreamCursor::class);

        foreach ([$cursor1, $cursor2, $cursor3, $cursor4] as $cursor) {
            $cursor->method('_can_combine_with')->willReturn(true);
            $cursor->method('_combine_with')->willReturn($cursor);
        }

        // First call retains only 1 element
        $el1 = new MockLeafStreamElement("", $cursor1);

        // Second call retains 3 elements (more than remaining want_count=2)
        $el2 = new MockLeafStreamElement("", $cursor2);
        $el3 = new MockLeafStreamElement("", $cursor3);
        $el4 = new MockLeafStreamElement("", $cursor4);

        $inner_stream = $this->createMock(Stream::class);
        $filter = $this->createMock(StreamFilter::class);

        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$el1]),
                new StreamResult(true, [$el2, $el3, $el4])
            );

        $filter->expects($this->exactly(2))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([$el1], []),
                new StreamFilterResult([$el2, $el3, $el4], [])
            );

        $stream = new FilteredStream($inner_stream, $filter, 'test', 1, 0.0);
        $stream->slice_result = true;

        $result = $stream->enumerate($want_count);

        $this->assertSame(3, $result->get_size());
        $this->assertFalse($result->is_exhaustive(), "Stream should not be marked as exhausted because we sliced.");
    }
}
