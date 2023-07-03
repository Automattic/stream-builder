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

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamWeight;

/**
 * Class StreamWeightTest
 */
class StreamWeightTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var null
     */
    protected $stream = null;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['awesome_stream'])
            ->getMock();
        $this->stream->expects($this->any())
            ->method('to_template')
            ->willReturn([
                '_type' => 'AmazingStream',
            ]);
    }

    /**
     * Test get_stream
     */
    public function test_get_stream()
    {
        $sw = new StreamWeight(2, $this->stream);
        $got_stream = $sw->get_stream();
        $this->assertSame('awesome_stream', $got_stream->get_identity());
    }

    /**
     * Test get_weight
     */
    public function test_get_weight()
    {
        $sw = new StreamWeight(2, $this->stream);
        $got_weight = $sw->get_weight();
        $this->assertSame(2.0, $got_weight);
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $sw = new StreamWeight(2, $this->stream);
        $serialized_string = $sw->to_template();
        $this->assertSame([
            '_type' => StreamWeight::class,
            'weight' => 2.0,
            'stream' => [
                '_type' => 'AmazingStream',
            ],
        ], $serialized_string);
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => StreamWeight::class,
            'weight' => 10.0,
            'stream' => [
                '_type' => NullStream::class,
            ],
        ];
        $context = new StreamContext($template, []);

        $sw = StreamWeight::from_template($context);
        $this->assertSame($sw->get_weight(), 10.0);
        $this->assertSame($sw->to_template(), $template);
    }
}
