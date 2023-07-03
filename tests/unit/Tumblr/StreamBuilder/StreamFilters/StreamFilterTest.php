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
        $released_el = $el;
        $filter->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([], [$released_el]));

        $tracer = $this->getMockBuilder(StreamTracer::class)->getMockForAbstractClass();

        $filter->filter([$el], null, $tracer);
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
