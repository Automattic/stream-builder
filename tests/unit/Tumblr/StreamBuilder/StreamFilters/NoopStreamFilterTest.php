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

use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilters\NoopStreamFilter;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;

/**
 * Class NoopStreamFilterTest
 */
class NoopStreamFilterTest extends \PHPUnit\Framework\TestCase
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
        $this->sf = new NoopStreamFilter('test');
    }

    /**
     * Test get_cache_key
     */
    public function test_get_cache_key()
    {
        $this->assertSame('NoopStreamFilter', $this->sf->get_cache_key());
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $this->assertSame(['_type' => NoopStreamFilter::class], $this->sf->to_template());
    }

    /**
     * Test filter
     */
    public function test_filter()
    {
        $el1 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool1'])->getMockForAbstractClass();
        $el2 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool2'])->getMockForAbstractClass();

        $sf_result = $this->sf->filter([$el1, $el2]);
        $released = $sf_result->get_released();

        $this->assertSame([$el1, $el2], $sf_result->get_retained());
        $this->assertSame([], $released);
    }
}
