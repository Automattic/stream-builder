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

use Tumblr\StreamBuilder\SignalFetchers\SignalFetcher;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Tests for SignalFetcher
 */
class SignalFetcherTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Tests that the tracer is called as expected for a signal fetcher that throws an exception.
     * @return void
     */
    public function test_misbehaving_fetch()
    {
        $tracer = $this->getMockBuilder(StreamTracer::class)
            ->getMockForAbstractClass();

        $sf = $this->getMockBuilder(SignalFetcher::class)
            ->setConstructorArgs(['fetcher'])
            ->getMockForAbstractClass();

        $sf->expects($this->once())->method('fetch_inner')->willThrowException(new \RangeException('yes i did'));

        /** @var SignalFetcher $sf */
        try {
            $sf->fetch([], $tracer);
        } catch (\RangeException $re) {
            $this->assertSame('yes i did', $re->getMessage());
        }
    }
}
