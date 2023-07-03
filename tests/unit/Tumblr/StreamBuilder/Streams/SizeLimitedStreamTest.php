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

use Tests\Unit\Tumblr\StreamBuilder\StreamBuilderTest;
use Tests\Unit\Tumblr\StreamBuilder\TestUtils;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\Interfaces\Log;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\ConcatenatedStream;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\SizeLimitedStream;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class SizeLimitedStreamTest
 */
class SizeLimitedStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor.
     */
    public function test_constructor_failure()
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        new SizeLimitedStream($stream, 0, 'invalid_stream');
    }

    /**
     * Test enumerate with inappropriate cursor.
     */
    public function test_enumerate_failure()
    {
        $this->expectException(InappropriateCursorException::class);
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $size_limited_stream = new SizeLimitedStream($stream, 5, 'invalid_stream');

        $size_limited_stream->enumerate(10, new MultiCursor([]));
    }

    /**
     * Test enumerate
     */
    public function test_enumerate()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el, $el, $el, $el]));

        $size_limited_stream = new SizeLimitedStream($stream, 5, 'amazing_size_limited_stream');
        $result = $size_limited_stream->enumerate(4);
        $this->assertSame(4, $result->get_size());

        $result = $size_limited_stream->enumerate(4, $result->get_combined_cursor());
        $this->assertSame(1, $result->get_size());

        $result = $size_limited_stream->enumerate(4, $result->get_combined_cursor());
        $this->assertSame(0, $result->get_size());
    }

    /**
     * Test enumerate
     */
    public function test_enumerate_exact_count()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $stream->expects($this->any())->method('_enumerate')
            ->will($this->onConsecutiveCalls(
                new StreamResult(true, [$el, $el, $el, $el]),
                new StreamResult(true, [])
            ));

        $size_limited_stream = new SizeLimitedStream($stream, 4, 'amazing_size_limited_stream');
        $result = $size_limited_stream->enumerate(4);
        $this->assertSame(4, $result->get_size());
        $this->assertTrue($result->is_exhaustive());

        $result = $size_limited_stream->enumerate(4, $result->get_combined_cursor());
        $this->assertSame(0, $result->get_size());
    }

    /**
     * Test enumerate when not enough elements are returned.
     */
    public function test_enumerate_less_count()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $stream->expects($this->any())->method('_enumerate')
            ->will($this->onConsecutiveCalls(
                new StreamResult(true, [$el, $el, $el, $el]),
                new StreamResult(true, [])
            ));

        $size_limited_stream = new SizeLimitedStream($stream, 5, 'amazing_size_limited_stream');
        $result = $size_limited_stream->enumerate(5);
        $this->assertSame(4, $result->get_size());
        $this->assertTrue($result->is_exhaustive());

        $result = $size_limited_stream->enumerate(4, $result->get_combined_cursor());
        $this->assertSame(0, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test enumerate when not enough elements are returned, but larger than size.
     */
    public function test_enumerate_less_count_larger_than_size()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $stream->expects($this->any())->method('_enumerate')
            ->will($this->onConsecutiveCalls(
                new StreamResult(false, [$el, $el, $el, $el]),
                new StreamResult(true, [$el, $el])
            ));

        $size_limited_stream = new SizeLimitedStream($stream, 2, 'amazing_size_limited_stream');
        $result = $size_limited_stream->enumerate(5);
        $this->assertSame(2, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test enumerate when not enough elements are returned.
     */
    public function test_enumerate_more_count()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $stream->expects($this->any())->method('_enumerate')
            ->will($this->onConsecutiveCalls(
                new StreamResult(false, [$el, $el, $el, $el, $el]),
                new StreamResult(true, [])
            ));

        $size_limited_stream = new SizeLimitedStream($stream, 4, 'amazing_size_limited_stream');
        $result = $size_limited_stream->enumerate(4);
        $this->assertSame(4, $result->get_size());
        $this->assertTrue($result->is_exhaustive());
    }

    /**
     * Test enumerate when not enough elements are returned.
     */
    public function testEnumerateWithSmallBalance(): void
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $stream->expects($this->once())
            ->method('_enumerate')
            ->with($this->equalTo(5));
        $size_limited_stream = new SizeLimitedStream($stream, 5, 'amazing_size_limited_stream');
        $size_limited_stream->enumerate(20);
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $stream->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'amazing_inner_stream',
            ]);
        $size_limited_stream = new SizeLimitedStream($stream, 10, 'amazing_size_limited_stream');
        $this->assertSame($size_limited_stream->to_template(), [
            '_type' => SizeLimitedStream::class,
            'stream' => [
                '_type' => 'amazing_inner_stream',
            ],
            'limit' => 10,
        ]);
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $stream = new NullStream('size_limited/stream');
        $size_limited_stream = new SizeLimitedStream($stream, 10, 'size_limited');
        $template = $size_limited_stream->to_template();
        $context = new StreamContext($template, [], null, 'size_limited');
        TestUtils::assertSameRecursively($size_limited_stream, SizeLimitedStream::from_template($context));
    }

    /**
     * Test set query string
     * @return void
     */
    public function testSetQueryString()
    {
        $stream = $this->getMockBuilder(ConcatenatedStream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stream->expects($this->once())
            ->method('setQueryString');
        $size_limited_stream = new SizeLimitedStream($stream, 10, 'size_limited');
        $stream->expects($this->once())->method('setQueryString');

        $size_limited_stream->setQueryString('music');
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
        $size_limited_stream = new SizeLimitedStream($stream, 10, 'size_limited');

        $dependency_bag = $this->getMockBuilder(DependencyBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $log = $this->getMockBuilder(Log::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dependency_bag->expects($this->once())->method('getLog')->willReturn($log);
        $log->expects($this->once())->method('warning');
        StreamBuilderTest::overrideStreamBuilderInit($dependency_bag);

        $size_limited_stream->setQueryString('music');
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
