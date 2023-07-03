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

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilters\CachedStreamFilter;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\TransientCacheProvider;
use const Tumblr\StreamBuilder\SECONDS_PER_DAY;

/**
 * Class CachedStreamFilterTest
 */
class CachedStreamFilterTest extends \PHPUnit\Framework\TestCase
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
        $cached_sf = new CachedStreamFilter('ello', $this->sf, new TransientCacheProvider(), 'v2.5');
        $this->assertSame('Cached(filter_id,v2.5)', $cached_sf->get_cache_key());
    }


    /**
     * Test uncacheable
     * @return void
     */
    public function test_uncacheable()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\UncacheableStreamFilterException::class);
        $this->sf->expects($this->once())
            ->method('get_cache_key')
            ->willReturn(null);
        new CachedStreamFilter('ello', $this->sf, new TransientCacheProvider(), 'v2.5');
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $this->sf->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'filter_bar',
            ]);
        $this->sf->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('yas_im_cacheable_dude');
        $cached_sf = new CachedStreamFilter('ello', $this->sf, new TransientCacheProvider(), 'v2.5');
        $this->assertSame([
            '_type' => CachedStreamFilter::class,
            'stream_filter' => [
                '_type' => 'filter_bar',
            ],
            "version" => 'v2.5',
            "ttl_seconds_retain" => 0,
            "ttl_seconds_release" => SECONDS_PER_DAY,
        ], $cached_sf->to_template());
    }

    /**
     * Test filter
     */
    public function test_filter()
    {
        $el1 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool1'])->getMockForAbstractClass();
        $el2 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool2'])->getMockForAbstractClass();

        $el1->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('El(1)');

        $el2->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('El(2)');

        $this->sf->expects($this->once())
            ->method('get_cache_key')
            ->willReturn('amazing_filter');

        /** @var CacheProvider|\PHPUnit\Framework\MockObject\MockObject $provider */
        $provider = $this->getMockBuilder(CacheProvider::class)->getMockForAbstractClass();
        $provider->expects($this->once())
            ->method('get_multi')
            ->willReturn([
                'amazing_filter:v2.6:El(1)' => 'P',
                'amazing_filter:v2.6:El(2)' => 'F',
            ]);

        $cached_sf = new CachedStreamFilter('ello', $this->sf, $provider, 'v2.6');
        $elements = [$el1, $el2];
        $sf_result = $cached_sf->filter($elements);

        $released = $sf_result->get_released();

        $this->assertSame([$el1], $sf_result->get_retained());
        $this->assertSame([$el2], $released);
    }
}
