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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\RankedStream;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class RankedStreamTest
 */
class RankedStreamTest extends \PHPUnit\Framework\TestCase
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

        /** @var StreamRanker|\PHPUnit\Framework\MockObject\MockObject $ranker */
        $ranker = $this->getMockBuilder(StreamRanker::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $ranker->expects($this->any())->method('pre_fetch')->willReturn(null);

        $ranker->expects($this->any())
            ->method('rank_inner')
            ->with([$e1, $e2, $e3, $e4, $e5], null)
            ->willReturn([$e5, $e4, $e3, $e2, $e1]);

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->with(5, null, null)
            ->willReturn(new StreamResult(false, [$e1, $e2, $e3, $e4, $e5]));

        $stream = new RankedStream($inner_stream, $ranker, 'amazing_ranked_stream');
        $result = $stream->enumerate(5);
        $this->assertSame([$e5, $e4, $e3, $e2, $e1], $result->get_elements());
        $this->assertFalse($result->is_exhaustive());
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $template = [
            '_type' => RankedStream::class,
            'inner' => [
                '_type' => NullStream::class,
            ],
            'ranker' => [
                '_type' => DummyStreamRanker::class,
            ],
        ];

        $ranker = new DummyStreamRanker('stupid_ranker');
        $inner_stream = new NullStream('stupid_stream');
        $ranked_stream = new RankedStream($inner_stream, $ranker, '10', 'amazing_ranked_stream');
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
        $this->assertSame($template, RankedStream::from_template($context)->to_template());
    }

    /**
     * Test the get_ranker method
     */
    public function test_get_ranker()
    {
        $template = [
            '_type' => RankedStream::class,
            'inner' => [
                '_type' => NullStream::class,
            ],
            'ranker' => [
                '_type' => DummyStreamRanker::class,
            ],
        ];
        $context = new StreamContext($template, []);
        $ranker = RankedStream::from_template($context)->get_ranker();
        $this->assertTrue($ranker instanceof DummyStreamRanker);
    }

    /**
     * Test when ranker failed, we return original sequence of elements.
     */
    public function testRankFailure(): void
    {
        $e1 = new MockMaxStreamElement(1, 'p1', new MockMaxCursor(1));
        $e2 = new MockMaxStreamElement(2, 'p2', new MockMaxCursor(2));
        $e3 = new MockMaxStreamElement(3, 'p3', new MockMaxCursor(3));
        $e4 = new MockMaxStreamElement(4, 'p4', new MockMaxCursor(4));
        $e5 = new MockMaxStreamElement(5, 'p5', new MockMaxCursor(5));

        /** @var StreamRanker|\PHPUnit\Framework\MockObject\MockObject $ranker */
        $ranker = $this->getMockBuilder(StreamRanker::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $ranker->expects($this->any())->method('rank_inner')->willThrowException(new \InvalidArgumentException());

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $inner_stream */
        $inner_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->with(5, null, null)
            ->willReturn(new StreamResult(false, [$e1, $e2, $e3, $e4, $e5]));

        $stream = new RankedStream($inner_stream, $ranker, 'amazing_ranked_stream');
        $result = $stream->enumerate(5);
        $this->assertSame([$e1, $e2, $e3, $e4, $e5], $result->get_elements());
        $this->assertFalse($result->is_exhaustive());
    }
}
