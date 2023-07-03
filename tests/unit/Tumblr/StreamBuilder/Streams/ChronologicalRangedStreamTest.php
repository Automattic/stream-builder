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

use Test\Mock\Tumblr\StreamBuilder\Interfaces\MockedUser;
use Test\Tumblr\StreamBuilder\Streams\TestingRankableChronoStream;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\Streams\ChronologicalRangedStream;
use Tumblr\StreamBuilder\Streams\Stream;
use const Tumblr\StreamBuilder\SECONDS_PER_DAY;
use const Tumblr\StreamBuilder\SECONDS_PER_HOUR;

/**
 * Class SizeLimitedStreamTest
 */
class ChronologicalRangedStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockedUser|\PHPUnit\Framework\MockObject\MockObject User for this test
     */
    private $user;

    /** @var TestingRankableChronoStream Inner stream */
    private TestingRankableChronoStream $inner;

    /** @var ChronologicalRangedStream */
    private ChronologicalRangedStream $stream;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->user = new MockedUser(1);

        $this->inner = new TestingRankableChronoStream(
            'test',
            []
        );

        $this->stream = new ChronologicalRangedStream(
            $this->inner,
            'test',
            SECONDS_PER_HOUR,
            SECONDS_PER_DAY
        );
    }

    /**
     * Test constructor.
     */
    public function test_constructor_failure()
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        new ChronologicalRangedStream($stream, 'invalid_stream', 10, 20);
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $this->assertSame($this->stream->to_template(), [
            '_type' => ChronologicalRangedStream::class,
            'inner' => $this->inner->to_template(),
            'min_age' => SECONDS_PER_HOUR,
            'max_age' => SECONDS_PER_DAY,
        ]);
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = $this->stream->to_template();
        $context = new StreamContext($template, ['user' => $this->user], null, 'test');
        $built_stream = ChronologicalRangedStream::from_template($context)->to_template();
        $this->assertSame($template['_type'], $built_stream['_type']);
        $this->assertSame($template['min_age'], $built_stream['min_age']);
    }
}
