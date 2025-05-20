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

use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\CachedStream;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\TransientCacheProvider;

/**
 * Test for CachedStream
 */
class CachedStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * When inner stream enumeration result is empty, it should also be cached.
     */
    public function testEmptyResultCache()
    {
        $inner_stream = new NullStream('inner');
        $cache_provider = $this->getMockBuilder(CacheProvider::class)
            ->getMock();
        $cache_provider
            ->expects($this->once())
            ->method('set')
            ->willReturn('');
        $cache_provider
            ->expects($this->never())
            ->method('set_multi')
            ->willReturn('');
        $stream = $this->getMockBuilder(CachedStream::class)
            ->setConstructorArgs([$inner_stream, $cache_provider, 5, 10, 10, 'ello'])
            ->getMockForAbstractClass();
        $stream->enumerate(10);
    }

    /**
     * Test when cache miss, it should call inner stream to get StreamResult.
     */
    public function testCacheMiss()
    {
        $mocked_element = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['ello', null])
            ->getMockForAbstractClass();
        $inner_stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['ello'])
            ->getMockForAbstractClass();
        $inner_stream->method('_enumerate')
            ->willReturn(new StreamResult(false, [$mocked_element, $mocked_element]));
        $cache_provider = $this->getMockBuilder(CacheProvider::class)
            ->getMock();
        $cache_provider
            ->expects($this->once())
            ->method('set')
            ->willReturn('');
        $cache_provider
            ->expects($this->never())
            ->method('set_multi')
            ->willReturn('');
        $stream = $this->getMockBuilder(CachedStream::class)
            ->setConstructorArgs([$inner_stream, $cache_provider, 5, 10, 10, 'ello'])
            ->getMockForAbstractClass();
        $stream->enumerate(10);
    }

    /**
     * Test when cache hit, it should not call inner stream to get StreamResult.
     */
    public function testCacheHit()
    {
        $inner_stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['ello'])
            ->getMockForAbstractClass();
        $inner_stream->method('_enumerate')
            ->willReturn(new StreamResult(false, []));
        $cache_provider = $this->getMockBuilder(CacheProvider::class)
            ->getMock();
        $cache_provider
            ->expects($this->never())
            ->method('set')
            ->willReturn('');
        $cache_provider
            ->expects($this->once())
            ->method('get')
            ->willReturn('sgnwjgnwj');
        $stream = $this->getMockBuilder(CachedStream::class)
            ->setConstructorArgs([$inner_stream, $cache_provider, 5, 10, 10, 'ello'])
            ->getMock();
        $mocked_element = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['ello', null])
            ->getMock();
        $stream->method('deserialize')
            ->willReturn(new StreamResult(false, [
                $mocked_element,
                $mocked_element,
            ]));
        $stream->enumerate(10);
    }

    /**
     * Test when the inner stream returns false for `can_enumerate`,
     * CachedStream returns empty results when enumerating, even when cache is hit.
     */
    public function testCacheHitCannotEnumerateInner()
    {
        // Create a test double for the inner stream that overrides can_enumerate
        $inner_stream = new class('ello') extends Stream {
            /**
             * @inheritDoc
             */
            #[\Override]
            protected function _enumerate(
                int $count,
                ?StreamCursor $cursor = null,
                ?StreamTracer $tracer = null,
                ?EnumerationOptions $option = null
            ): StreamResult {
                return new StreamResult(false, []);
            }

            /**
             * @inheritDoc
             */
            #[\Override]
            protected function can_enumerate(): bool
            {
                return false;
            }

            /**
             * @inheritDoc
             */
            #[\Override]
            public static function from_template(StreamContext $context)
            {
                return new self($context->get_current_identity());
            }
        };
        $cache_provider = $this->getMockBuilder(CacheProvider::class)
            ->getMock();
        $cache_provider
            ->expects($this->never())
            ->method('set')
            ->willReturn('');
        // should not read from cache when "can_enumerate" returns false
        $cache_provider
            ->expects($this->never())
            ->method('get')
            ->willReturn('sgnwjgnwj');
        $stream = $this->getMockBuilder(CachedStream::class)
            ->setConstructorArgs([$inner_stream, $cache_provider, 5, 10, 10, 'ello'])
            ->getMock();
        $stream->method('deserialize')
            ->willReturn(new StreamResult(false, []));
        $stream->enumerate(10);
    }

    /**
     * Test to_template
     */
    public function testToTemplate()
    {
        $inner_stream = new NullStream('inner');
        $cache_provider = new TransientCacheProvider();
        $stream = $this->getMockBuilder(CachedStream::class)
            ->setConstructorArgs([$inner_stream, $cache_provider, 5, 10, 10, 'ello'])
            ->getMockForAbstractClass();
        $template = $stream->to_template();
        $this->assertSame(10, $template[CachedStream::CACHE_TTL_COLUMN]);
        $this->assertSame((new NullStream('inner'))->to_template(), $template[CachedStream::STREAM_COLUMN]);
    }

    /**
     * Test cache stream result and second enumeration should get from cache.
     */
    public function testCacheAndGet()
    {
        $stream_result = new StreamResult(false, [
            new MockedPostRefElement(111, 123),
            new MockedPostRefElement(111, 123),
        ]);
        $inner_stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['ello'])
            ->getMockForAbstractClass();
        $inner_stream->method('_enumerate')
            ->willReturn($stream_result);
        $cache_provider = new TransientCacheProvider();
        $stream = $this->getMockBuilder(CachedStream::class)
            ->setConstructorArgs([$inner_stream, $cache_provider, 5, 10, 10, 'ello'])
            ->getMockForAbstractClass();
        $stream->method('_slice_result_with_cursor')
            ->willReturnCallback(function ($count, $inner_result) {
                return $inner_result;
            });
        $stream->enumerate(10);
        $actual_result = $stream->enumerate(10);
        $this->assertSame(
            array_map(function ($e) {
                return $e->get_post_id();
            }, $stream_result->get_elements()),
            array_map(function ($e) {
                return $e->get_post_id();
            }, $actual_result->get_elements())
        );
    }

    /**
     * Test cached result should be based on candidate count instead of enumeration count.
     */
    public function testCandidateCount()
    {
        $stream_result = new StreamResult(false, [
            new MockedPostRefElement(111, 123),
            new MockedPostRefElement(222, 123),
            new MockedPostRefElement(333, 123),
        ]);
        $inner_stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $inner_stream
            ->method('_enumerate')
            ->willReturn($stream_result);
        $cache_provider = new TransientCacheProvider();
        $stream = $this->getMockBuilder(CachedStream::class)
            ->setConstructorArgs([$inner_stream, $cache_provider, 5, 10, 10, 'ello'])
            ->getMockForAbstractClass();
        $stream->method('_slice_result_with_cursor')
            ->willReturnCallback(function ($count, $inner_result) {
                return $inner_result;
            });
        $stream->enumerate(1);
        $actual_result = $stream->enumerate(3);
        $this->assertSame(
            array_map(function ($e) {
                return $e->get_post_id();
            }, $stream_result->get_elements()),
            array_map(function ($e) {
                return $e->get_post_id();
            }, $actual_result->get_elements())
        );
    }
}
