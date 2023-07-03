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

use Tests\Unit\Tumblr\StreamBuilder\StreamBuilderTest;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\Interfaces\Log;
use Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilter;
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
     * Redo the dependency bag injection.
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        StreamBuilderTest::resetStreamBuilder();
    }
}
