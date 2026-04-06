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

namespace Tests\Unit\Tumblr\StreamBuilder\Streams;

use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tests\Unit\Tumblr\StreamBuilder\StreamBuilderTest;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\Interfaces\Log;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilter;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\ConcatenatedStream;
use Tumblr\StreamBuilder\Streams\CursorlessFilteredStream;
use Tumblr\StreamBuilder\Streams\Stream;

class CursorlessFilteredStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test set query string
     * @return void
     */
    public function testSetQueryString()
    {
        $stream = $this->getMockBuilder(ConcatenatedStream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stream->expects($this->once())->method('setQueryString');
        $identity = 'test';
        $cursorless_filtered_stream = new CursorlessFilteredStream(
            $stream,
            new CompositeStreamFilter($identity, []),
            $identity
        );

        $cursorless_filtered_stream->setQueryString('music');
    }

    /**
     * Test logging error works
     * @return void
     */
    public function testLogError()
    {
        /** @var Stream $stream Let's set a Stream that doesn't have setQueryString method, so we can test the error log */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $identity = 'test';
        $cursorless_filtered_stream = new CursorlessFilteredStream(
            $stream,
            new CompositeStreamFilter($identity, []),
            $identity
        );

        $dependency_bag = $this->getMockBuilder(DependencyBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $log = $this->getMockBuilder(Log::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dependency_bag->expects($this->once())->method('getLog')->willReturn($log);
        $log->expects($this->once())->method('warning');
        StreamBuilderTest::overrideStreamBuilderInit($dependency_bag);

        $cursorless_filtered_stream->setQueryString('music');
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

        $filtered_stream = new CursorlessFilteredStream($inner_stream, $filter, 'test', 1, 0.0);
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

    /**
     * Tests that skip_empty_pages (default true) allows scanning past fully-filtered pages.
     *
     * Simulates 3 consecutive batches where all elements are filtered out,
     * followed by a batch with a passing element. With retry_count=1 and
     * skip_empty_pages=true, the stream should reach the 4th batch because
     * empty pages don't consume retries.
     */
    public function test_skip_empty_pages_continues_past_filtered_batches()
    {
        $keep = new MockedPostRefElement(1, 100);
        $drop1 = new MockedPostRefElement(2, 200);
        $drop2 = new MockedPostRefElement(3, 300);
        $drop3 = new MockedPostRefElement(4, 400);

        $inner_stream = $this->createMock(Stream::class);
        $inner_stream->expects($this->exactly(4))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$drop1]),
                new StreamResult(false, [$drop2]),
                new StreamResult(false, [$drop3]),
                new StreamResult(true, [$keep])
            );

        $filter = $this->createMock(StreamFilter::class);
        $filter->expects($this->exactly(4))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([], [$drop1]),
                new StreamFilterResult([], [$drop2]),
                new StreamFilterResult([], [$drop3]),
                new StreamFilterResult([$keep], [])
            );

        // retry_count=1, skip_empty_pages=true (default)
        $filtered_stream = new CursorlessFilteredStream($inner_stream, $filter, 'test', 1, 0.0);
        $result = $filtered_stream->enumerate(1);

        $this->assertSame(1, $result->get_size());
        $this->assertSame($keep, $result->get_elements()[0]->get_original_element());
    }

    /**
     * Tests that skip_empty_pages=false preserves the old behavior where
     * fully-filtered pages consume retries.
     *
     * With retry_count=1, only 2 batches are fetched. Both are fully filtered,
     * so the result is empty.
     */
    public function test_skip_empty_pages_false_preserves_old_behavior()
    {
        $drop1 = new MockedPostRefElement(1, 100);
        $drop2 = new MockedPostRefElement(2, 200);

        $inner_stream = $this->createMock(Stream::class);
        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$drop1]),
                new StreamResult(false, [$drop2])
            );

        $filter = $this->createMock(StreamFilter::class);
        $filter->expects($this->exactly(2))
            ->method('filter_inner')
            ->willReturnOnConsecutiveCalls(
                new StreamFilterResult([], [$drop1]),
                new StreamFilterResult([], [$drop2])
            );

        $filtered_stream = new CursorlessFilteredStream($inner_stream, $filter, 'test', 1, 0.0, false);
        $result = $filtered_stream->enumerate(1);

        $this->assertSame(0, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Tests that skip_empty_pages terminates when the inner stream is exhausted,
     * even if retry budget has not been consumed.
     */
    public function test_skip_empty_pages_terminates_on_inner_exhaustion()
    {
        $drop = new MockedPostRefElement(1, 100);

        $inner_stream = $this->createMock(Stream::class);
        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$drop]),
                new StreamResult(true, [])
            );

        $filter = $this->createMock(StreamFilter::class);
        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([], [$drop]));

        $filtered_stream = new CursorlessFilteredStream($inner_stream, $filter, 'test', 2, 0.0);
        $result = $filtered_stream->enumerate(1);

        $this->assertSame(0, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Redo the dependency bag injection.
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        StreamBuilderTest::resetStreamBuilder();
    }
}
