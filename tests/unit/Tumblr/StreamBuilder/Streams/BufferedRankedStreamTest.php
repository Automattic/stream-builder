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
use Test\Tumblr\StreamBuilder\StreamRankers\DummyStreamRanker;
use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\BufferedRankedStream;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\TransientCacheProvider;
use function array_map;

/**
 * Class BufferedRankedStreamTest
 */
class BufferedRankedStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test enumerate
     */
    public function test_enumerate()
    {
        $e1 = new MockMaxStreamElement(1, 'p1', new MockMaxCursor(1));
        $e2 = new MockMaxStreamElement(2, 'p2', new MockMaxCursor(2));
        $e3 = new MockMaxStreamElement(3, 'p3', new MockMaxCursor(3));
        $e4 = new MockMaxStreamElement(4, 'p4', new MockMaxCursor(4));
        $e5 = new MockMaxStreamElement(5, 'p5', new MockMaxCursor(5));
        $e6 = new MockMaxStreamElement(6, 'p6', new MockMaxCursor(6));

        /** @var StreamRanker|\PHPUnit\Framework\MockObject\MockObject $ranker */
        $ranker = $this->getMockBuilder(StreamRanker::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $ranker->expects($this->exactly(2))
            ->method('rank_inner')
            ->withConsecutive(
                [[$e1, $e2, $e3, $e4, $e5], null],
                [[$e3, $e2, $e1, $e6], null]
            )
            ->willReturnOnConsecutiveCalls(
                [$e5, $e4, $e3, $e2, $e1],
                [$e3, $e6, $e2, $e1]
            );

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $inner_stream->expects($this->exactly(2))
            ->method('_enumerate')
            ->withConsecutive(
                [5, null, null],
                [3]
            )
            ->willReturnOnConsecutiveCalls(
                new StreamResult(false, [$e1, $e2, $e3, $e4, $e5]),
                new StreamResult(true, [$e6])
            );

        /** @var CacheProvider|\PHPUnit\Framework\MockObject\MockObject $cache_provider */
        $cache_provider = $this->getMockBuilder(CacheProvider::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $ranked_stream = new BufferedRankedStream($inner_stream, $ranker, 3, 'amazing_ranked_stream', $cache_provider);
        $result = $ranked_stream->enumerate(2);
        $this->assertSame([$e5, $e4], array_map(function (DerivedStreamElement $e) {
            return $e->get_original_element();
        }, $result->get_elements()));

        $result = $ranked_stream->enumerate(3, $result->get_combined_cursor());
        $this->assertSame([$e3, $e6, $e2], array_map(function (DerivedStreamElement $e) {
            return $e->get_original_element();
        }, $result->get_elements()));

        return $ranked_stream;
    }

    /**
     * Test enumerate with wrong cursor.
     * @depends test_enumerate
     * @param BufferedRankedStream $stream The stream to be tested.
     */
    public function test_enumerate_wrong_cursor(BufferedRankedStream $stream)
    {
        $this->expectException(InappropriateCursorException::class);
        $stream->enumerate(10, new MultiCursor([]));
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $template = [
            '_type' => BufferedRankedStream::class,
            'inner' => [
                '_type' => NullStream::class,
            ],
            'ranker' => [
                '_type' => DummyStreamRanker::class,
            ],
            'overfetch_count' => 10,
        ];

        $ranker = new DummyStreamRanker('stupid_ranker');
        $inner_stream = new NullStream('stupid_stream');
        $cache_provider = new TransientCacheProvider();
        $ranked_stream = new BufferedRankedStream($inner_stream, $ranker, 10, 'amazing_ranked_stream', $cache_provider);
        $this->assertSame($template, $ranked_stream->to_template());

        return $template;
    }

    /**
     * @depends test_to_template
     * @param array $template The template.
     */
    public function test_from_template(array $template)
    {
        $context = new StreamContext($template, []);
        $this->assertSame($template, BufferedRankedStream::from_template($context)->to_template());
    }
}
