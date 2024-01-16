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

use Test\Tumblr\StreamBuilder\StreamCursors\MockMaxCursor;
use Test\Tumblr\StreamBuilder\StreamElements\MockMaxStreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamTracers\DebugStreamTracer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Class StreamFilterTest
 */
class StreamFilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the concrete implementation in this filter call.
     */
    public function test_filter()
    {
        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)
            ->setConstructorArgs(['ello'])
            ->setMethods(['filter_inner'])
            ->getMockForAbstractClass();
        $el = new MockMaxStreamElement(123, 'awesome_provider', new MockMaxCursor(456));
        $el2 = new MockMaxStreamElement(234, 'awesome_provider', new MockMaxCursor(789));
        $released_el = $el;
        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([], [$released_el]));

        $tracer = new DebugStreamTracer();

        $filter->filter([$el, $el2], null, $tracer);
        $this->assertCount(3, $tracer->get_output());

        // Example output:
        // [2024-01-16T09:28:33-05:00]: op=filter sender=ello[Mock_StreamFilter_e7231ac5] status=begin other={"count":2}
        // [2024-01-16T09:56:32-05:00]: op=filter sender=ello[Mock_StreamFilter_3aaefcd3] status=end start_time=1705416992.4759 duration=2.0980834960938E-5 other={"count":1}
        // [2024-01-16T09:56:32-05:00]: op=filter sender=ello[Mock_StreamFilter_3aaefcd3] status=release other={"target":"MockMaxStreamElement","meta_detail":"TEST_MockMaxElement(123)","filter_code":"Mock_StreamFilter_3aaefcd3"}
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=begin.other=\{\"count\":2\}/",
            $tracer->get_output()[0]
        );
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=end.start_time=.*duration=.*other=\{\"count\":1\}/",
            $tracer->get_output()[1]
        );
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=release.*other=\{\"target\":\"MockMaxStreamElement\",\"meta_detail\":\"TEST_MockMaxElement\(123\)\",\"filter_code\":\"Mock_StreamFilter_.*\".*\}/",
            $tracer->get_output()[2]
        );
    }


    /**
     * Test that the filter method calls filter_all when all elements are filtered
     */
    public function test_filter_all()
    {
        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)
            ->setConstructorArgs(['ello'])
            ->setMethods(['filter_inner'])
            ->getMockForAbstractClass();
        $el = new MockMaxStreamElement(123, 'awesome_provider', new MockMaxCursor(456));
        $el2 = new MockMaxStreamElement(234, 'awesome_provider', new MockMaxCursor(789));
        $released_el = $el;
        $released_el2 = $el2;
        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([], [$el, $el2]));

        $tracer = new DebugStreamTracer();

        $filter->filter([$el, $el2], null, $tracer);
        $this->assertCount(5, $tracer->get_output());

        // Example output:
        // [2024-01-16T09:28:33-05:00]: op=filter sender=ello[Mock_StreamFilter_e7231ac5] status=begin other={"count":2}
        // [2024-01-16T09:56:32-05:00]: op=filter sender=ello[Mock_StreamFilter_3aaefcd3] status=end start_time=1705416992.4759 duration=2.0980834960938E-5 other={"count":2}
        // [2024-01-16T09:56:32-05:00]: op=filter sender=ello[Mock_StreamFilter_3aaefcd3] status=release other={"target":"MockMaxStreamElement","meta_detail":"TEST_MockMaxElement(123)","filter_code":"Mock_StreamFilter_3aaefcd3"}
        // [2024-01-16T10:06:02-05:00]: op=filter sender=ello[Mock_StreamFilter_bd6d5ece] status=release other={"target":"MockMaxStreamElement","meta_detail":"TEST_MockMaxElement(234)","filter_code":"Mock_StreamFilter_bd6d5ece"}
        // [2024-01-16T10:06:02-05:00]: op=filter sender=ello[Mock_StreamFilter_bd6d5ece] status=release_all other={"count":2}
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=begin.other=\{\"count\":2\}/",
            $tracer->get_output()[0]
        );
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=end.start_time=.*duration=.*other=\{\"count\":2\}/",
            $tracer->get_output()[1]
        );
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=release.*other=\{\"target\":\"MockMaxStreamElement\",\"meta_detail\":\"TEST_MockMaxElement\(123\)\",\"filter_code\":\"Mock_StreamFilter_.*\".*\}/",
            $tracer->get_output()[2]
        );
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=release.*other=\{\"target\":\"MockMaxStreamElement\",\"meta_detail\":\"TEST_MockMaxElement\(234\)\",\"filter_code\":\"Mock_StreamFilter_.*\".*\}/",
            $tracer->get_output()[3]
        );
        $this->assertMatchesRegularExpression(
            "/op=filter.sender=ello\[Mock_StreamFilter_[a-z0-9A-Z]*\].status=release_all.other=\{\"count\":2\}/",
            $tracer->get_output()[4]
        );
    }


    /**
     * Test when filter throw exception
     */
    public function test_filter_with_exception()
    {
        $this->expectException(\Exception::class);

        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $filter */
        $filter = $this->getMockBuilder(StreamFilter::class)
            ->setConstructorArgs(['ello'])
            ->setMethods(['filter_inner'])
            ->getMockForAbstractClass();
        $filter->expects($this->once())
            ->method('filter_inner')
            ->willThrowException(new \Exception('whoops'));

        $tracer = $this->getMockBuilder(StreamTracer::class)->getMockForAbstractClass();

        $filter->filter([], null, $tracer);
    }
}
