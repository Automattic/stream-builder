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

use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilters\StreamElementFilter;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function get_class;

/**
 * Class StreamElementFilterTestCase
 */
abstract class StreamElementFilterTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Test filter
     */
    public function test_filter()
    {
        $el1 = $this->getMockBuilder(LeafStreamElement::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $el1->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('LeafStreamElement(1)');

        $el2 = $this->getMockBuilder(LeafStreamElement::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $el2->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('LeafStreamElement(2)');

        /** @var StreamElementFilter|\PHPUnit\Framework\MockObject\MockObject $sef */
        $sef = $this->getMockForAbstractClass(StreamElementFilter::class, ['ello']);
        $sef->expects($this->any())->method('should_release')->willReturnCallback(
            function ($param) {
                /** @var StreamElement $param */
                switch ($param->get_cache_key()) {
                    case 'LeafStreamElement(1)':
                        return true;
                    case 'LeafStreamElement(2)':
                        return false;
                    default:
                        return null;
                }
            }
        );

        $sf_result = $sef->filter([$el1, $el2]);
        $released = $sf_result->get_released();
        /** @var StreamElement $el2 */
        $el2->add_debug_info(
            StreamFilter::LOGGING_HEADER,
            StreamTracer::META_FILTER_CODE,
            get_class($sef)
        );
        $this->assertEquals([$el1], $sf_result->get_retained());
        $this->assertEquals([$el2], $released);
    }
}
