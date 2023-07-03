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
use Tumblr\StreamBuilder\StreamFilters\InverseFilter;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamTracers\DebugStreamTracer;

/**
 * Class InverseFilterTest
 */
class InverseFilterTest extends \PHPUnit\Framework\TestCase
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
     * Test get_identity
     */
    public function test_get_identity()
    {
        $this->sf->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('filter_id');
        $inverse_sf = new InverseFilter('ello', $this->sf);
        $this->assertSame('Inverse(filter_id)', $inverse_sf->get_cache_key());
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $this->sf->expects($this->once())
            ->method('to_template')
            ->willReturn(['_type' => 'filter_bar']);
        $inverse_sf = new InverseFilter('ello', $this->sf);
        $this->assertSame([
            '_type' => InverseFilter::class,
            'stream_filter' => [
                '_type' => 'filter_bar',
            ],
        ], $inverse_sf->to_template());
    }

    /**
     * Test filter
     */
    public function test_filter()
    {
        $el1 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $el2 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $this->sf->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el1, $el2], []));

        $inverse_sf = new InverseFilter('ello', $this->sf);
        $sf_result = $inverse_sf->filter([$el1, $el2]);
        $this->assertSame([], $sf_result->get_retained());
        $this->assertSame(
            [$el1, $el2],
            $sf_result->get_released()
        );
    }

    /**
     * Test filter
     */
    public function test_filter_trace()
    {
        $el1 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $el2 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $this->sf->expects($this->once())
            ->method('filter_inner')
            ->willReturn(new StreamFilterResult([$el1, $el2], []));

        $inverse_sf = new InverseFilter('ello', $this->sf);
        $tracer = new DebugStreamTracer();
        $sf_result = $inverse_sf->filter([$el1, $el2], null, $tracer);
        $output = $tracer->get_output();
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=filter sender=ello\\[InverseFilter\\] status=begin .*?/',
            $output[0]
        );
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=filter sender=ello\\[Mock_StreamFilter.*?\\] status=begin .*?/',
            $output[1]
        );
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=filter sender=ello\\[Mock_StreamFilter.*?\\] status=end .*?/',
            $output[2]
        );
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=filter sender=ello\\[InverseFilter\\] status=end .*?/',
            $output[3]
        );
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=filter sender=ello\\[InverseFilter\\] status=release .*?/',
            $output[4]
        );
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=filter sender=ello\\[InverseFilter\\] status=release .*?/',
            $output[5]
        );
    }
}
