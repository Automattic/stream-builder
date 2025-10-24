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
     * Redo the dependency bag injection.
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        StreamBuilderTest::resetStreamBuilder();
    }
}
