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

use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilter;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;

/**
 * Class CompositeStreamFilterTest
 */
class CompositeStreamFilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $sf
     */
    protected $sf;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->sf = $this->getMockBuilder(StreamFilter::class)
            ->setConstructorArgs(['ello'])
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
    }


    /**
     * Test constructor failure
     */
    public function test_constructor_failure()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new CompositeStreamFilter('ello', [new \stdClass()]);
    }

    /**
     * Test get_identity
     */
    public function test_get_identity()
    {
        $this->sf->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('filter_id');
        $composite_sf = new CompositeStreamFilter('ello', [$this->sf]);
        $this->assertSame('Composite(filter_id)', $composite_sf->get_cache_key());
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $this->sf->expects($this->once())
            ->method('to_template')
            ->willReturn(
                [
                    '_type' => 'filter_bar',
                ]
            );
        $composite_sf = new CompositeStreamFilter('ello', [$this->sf]);
        $this->assertSame([
            '_type' => CompositeStreamFilter::class,
            'stream_filter_array' => [
                [
                    '_type' => 'filter_bar',
                ],
            ],
        ], $composite_sf->to_template());
    }

    /**
     * Test filter
     */
    public function test_filter()
    {
        $el1 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $el2 */
        $el2 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $composite_sf = new CompositeStreamFilter('id', [$this->sf]);
        $this->assertSame(0, $composite_sf->filter([])->get_retained_count());
        $this->assertSame(0, $composite_sf->filter([])->get_released_count());

        $this->sf->expects($this->once())
            ->method('filter_inner')
            ->willReturn(
                new StreamFilterResult([$el1], [$el2])
            );

        $sf_result = $composite_sf->filter([$el1, $el2]);
        $released = $sf_result->get_released();

        $this->assertSame([$el1], $sf_result->get_retained());
        $this->assertSame([$el2], $released);
    }
}
