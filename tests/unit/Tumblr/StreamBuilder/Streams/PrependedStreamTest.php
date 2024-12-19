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

use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\PrependedStream;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class PrependedStreamTest
 * PrependStream test class.
 */
class PrependedStreamTest extends \PHPUnit\Framework\TestCase
{
    /** @var Stream */
    private $stream1 = null;
    /** @var Stream */
    private $stream2 = null;
    /** @var Stream */
    private $prepend_stream = null;
    /** @var StreamElement */
    private $stream_element1 = null;
    /** @var StreamElement */
    private $stream_element2 = null;
    /** @var StreamCursor */
    private $stream_cursor1 = null;
    /** @var StreamCursor */
    private $stream_cursor2 = null;

    /**
     * setup method for all test cases.
     * @return void
     */
    protected function setUp(): void
    {
        $this->stream_cursor1 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->stream_cursor2 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        // mock stream element and passed in mocked stream cursor
        // because StreamElement->get_cursor is final and can't be mocked.
        $this->stream_element1 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['cool', $this->stream_cursor1])->getMock();
        $this->stream_element2 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['cool', $this->stream_cursor2])->getMock();

        // mock element content
        $this->stream_element1->expects($this->any())->method('get_original_element')->willReturnSelf();
        $this->stream_element2->expects($this->any())->method('get_original_element')->willReturnSelf();
        // mock cursor's combine, and we only mock two cursors
        $this->stream_cursor1->expects($this->any())->method('_combine_with')->willReturnCallback(function ($other) {
            if ($other == $this->stream_cursor1) {
                return $this->stream_cursor2;
            } else {
                return $this->stream_cursor1;
            }
        });
        $this->stream_cursor2->expects($this->any())->method('_combine_with')->willReturnCallback(function ($other) {
            if ($other == $this->stream_cursor2) {
                return $this->stream_cursor1;
            } else {
                return $this->stream_cursor2;
            }
        });
        // always can combine for mocked cursor, not important here
        $this->stream_cursor1->expects($this->any())->method('_can_combine_with')->willReturn(true);
        $this->stream_cursor2->expects($this->any())->method('_can_combine_with')->willReturn(true);
        // callback for enumerate function, which will return StreamResult based on passed in parameters.
        // Because enumerate function is called by ConcatenatedStream so we can't do static return mock here.
        $enumerate_callback = function ($count, ?StreamCursor $cursor = null) {
            $offset = $cursor == null ? 0 : ($cursor === $this->stream_cursor1 ? 1 : 2);
            return new StreamResult(
                $offset + $count >= 2,
                array_slice([$this->stream_element1, $this->stream_element2], $offset, $count)
            );
        };
        $this->stream1 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream1'])->getMock();
        $this->stream1->expects($this->any())->method('_enumerate')->willReturnCallback($enumerate_callback);
        $this->stream2 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream2'])->getMock();
        $this->stream2->expects($this->any())->method('_enumerate')->willReturnCallback($enumerate_callback);
        $this->prepend_stream = new PrependedStream($this->stream1, 2, $this->stream2, "concatenatedStream1");
    }

    /**
     * Test constructor failure.
     * @return void
     */
    public function test_constructor_failure()
    {
        $this->expectException(\TypeError::class);
        new PrependedStream($this->stream1, 'invalid', $this->stream2, 'awesome_id');
    }

    /**
     * Test constructor with no limit provided.
     * @return void
     */
    public function test_constructor_default_limit()
    {
        $this->setUp();
        $stream = new PrependedStream($this->stream1, null, $this->stream2, 'awesome_id');
        $this->assertSame([
            '_type' => PrependedStream::class,
            'before' => $this->stream_element1->to_template(),
            'after' => $this->stream_element2->to_template(),
            'limit' => PrependedStream::DEFAULT_LIMIT,
        ], $stream->to_template());
    }

    /**
     * test if prepend works in normal case.
     * @return void
     */
    public function test_enumerate_1()
    {
        $res = $this->prepend_stream->enumerate(1);
        $this->assertSame(3, $res->get_size());
        $this->assertSame($this->stream_element1, $res->get_elements()[0]->get_original_element());
    }

    /**
     * test if enumerate 0, should throw exception because it would cause cursor to be null.
     * @return void
     */
    public function test_enumerate_0()
    {
        $this->expectException(\Exception::class);
        $res = $this->prepend_stream->enumerate(0);
        $this->assertSame(2, $res->get_size());
    }

    /**
     * test if enumerate 3, and size of prepended stream is only 2.
     * @return void
     */
    public function test_enumerate_3()
    {
        $res = $this->prepend_stream->enumerate(3);
        $this->assertSame(4, $res->get_size());
    }

    /**
     * test if enumerate 1 followed by 1 would work.
     * @return void
     */
    public function test_multiple_enumerate()
    {
        $res = $this->prepend_stream->enumerate(1);
        $res = $this->prepend_stream->enumerate(1, $res->get_combined_cursor());
        $this->assertSame(1, $res->get_size());
        $this->assertSame($this->stream_element2, $res->get_elements()[0]->get_original_element());
    }

    /**
     * test if multiple run works fine. every run should has valid result.
     * @return void
     */
    public function test_multiple_enumerate_2()
    {
        $res = $this->prepend_stream->enumerate(1);
        $res = $this->prepend_stream->enumerate(1, $res->get_combined_cursor());
        $this->assertSame(1, $res->get_size());
        $this->assertSame($this->stream_element2, $res->get_elements()[0]->get_original_element());
    }

    /**
     * test if multiple run works fine. every run should has valid result.
     * @return void
     */
    public function test_multiple_enumerate_3()
    {
        $res = $this->prepend_stream->enumerate(2);
        $res = $this->prepend_stream->enumerate(1, $res->get_combined_cursor());
        $this->assertSame(0, $res->get_size());
    }

    /**
     * Test when prepended stream failed, other streams should not be affected.
     */
    public function testIndividualStreamFailure(): void
    {
        $this->stream1 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['stream1'])
            ->getMock();
        $this->stream1->expects($this->once())
            ->method('_enumerate')
            ->willThrowException(new \InvalidArgumentException('whatever'));
        $this->prepend_stream = new PrependedStream($this->stream1, 2, $this->stream2, "concatenatedStream1");
        $result = $this->prepend_stream->enumerate(1);
        $elements = $result->get_elements();
        $this->assertEquals(1, count($elements));
        $this->assertEquals($this->stream_element1->get_element_id(), $elements[0]->get_element_id());
    }
}
