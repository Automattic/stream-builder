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

use ReflectionClass;
use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\ConcatenatedStream;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\SizeLimitedStream;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class ConcatenatedStreamTest
 */
class ConcatenatedStreamTest extends \PHPUnit\Framework\TestCase
{
    /** @var Stream */
    private $stream1 = null;
    /** @var Stream */
    private $stream2 = null;
    /** @var Stream */
    private $stream3 = null;
    /** @var null */
    private $null_stream = null;
    /** @var Stream */
    private $concatenated_stream = null;
    /** @var StreamElement */
    private $stream_element1 = null;
    /** @var StreamElement */
    private $stream_element2 = null;
    /** @var StreamCursor */
    private $stream_cursor1 = null;
    /** @var StreamCursor */
    private $stream_cursor2 = null;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $stream_element3;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $stream_element4;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->stream_cursor1 = $this->getMockForAbstractClass(StreamCursor::class, ['awesome_cursor']);
        $this->stream_cursor2 = $this->getMockForAbstractClass(StreamCursor::class, ['awesome_cursor']);
        // mock stream element and passed in mocked stream cursor because StreamElement->get_cursor is final and can't be mocked.
        $this->stream_element1 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $this->stream_cursor1])->getMock();
        $this->stream_element2 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $this->stream_cursor2])->getMock();
        // mock element content
        $this->stream_element1->expects($this->any())->method('get_original_element')->willReturnSelf();
        $this->stream_element2->expects($this->any())->method('get_original_element')->willReturnSelf();
        // mock cursor's combine, and we only mock two cursors
        $this->stream_cursor1->expects($this->any())->method('_combine_with')->willReturnCallback(function ($other) {
            if ($other != $this->stream_cursor1) {
                return $other;
            } else {
                return $this->stream_cursor1;
            }
        });
        $this->stream_cursor2->expects($this->any())->method('_combine_with')->willReturnCallback(function ($other) {
            if ($other != $this->stream_cursor2) {
                return $this->stream_cursor2;
            } else {
                return $other;
            }
        });
        // always can combine for mocked cursor, not important here
        $this->stream_cursor1->expects($this->any())->method('_can_combine_with')->willReturn(true);
        $this->stream_cursor2->expects($this->any())->method('_can_combine_with')->willReturn(true);
        // callback for enumerate function, which will return StreamResult based on passed in paramaters.
        // Because enumerate function is called by ConcatenatedStream so we can't do static return mock here.
        $enumerate_callback = function ($count, StreamCursor $cursor = null) {
            $offset = $cursor == null ? 0 : ($cursor == $this->stream_cursor1 ? 1 : 2);
            return new StreamResult($offset + $count >= 2, array_slice([$this->stream_element1, $this->stream_element2], $offset, $count));
        };
        $this->stream1 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream1'])->getMock();
        $this->stream1->expects($this->any())->method('_enumerate')->willReturnCallback($enumerate_callback);
        $this->stream2 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream2'])->getMock();
        $this->stream2->expects($this->any())->method('_enumerate')->willReturnCallback($enumerate_callback);
        $this->stream3 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream3'])->getMock();
        $this->stream3->expects($this->any())->method('_enumerate')->willReturnCallback($enumerate_callback);
        $this->concatenated_stream = new ConcatenatedStream([$this->stream1, $this->stream2, $this->stream3], "concatenatedStream1");
    }

    /**
     * test if concatenating works, most basic test.
     */
    public function test_concatenating()
    {
        $stream_field = (new ReflectionClass(get_class($this->concatenated_stream)))->getProperty("streams");
        $stream_field->setAccessible(true);
		$value = $stream_field->getValue($this->concatenated_stream);
		$value_count = is_countable($value) ? count($value) : 0;
        $this->assertSame(3, $value_count);
    }

    /**
     * test if ConcatenatedStream filtered out null streams
     */
    public function test_concatenating_with_null()
    {
        $concatenated_stream = new ConcatenatedStream([$this->stream1, $this->stream2, $this->stream3, $this->null_stream], "concatenatedStream2");
        $stream_field = (new ReflectionClass(get_class($concatenated_stream)))->getProperty("streams");
        $stream_field->setAccessible(true);
		$value = $stream_field->getValue($this->concatenated_stream);
		$value_count = is_countable($value) ? count($value) : 0;
        $this->assertSame(3, $value_count);
    }

    /**
     * test only enumerate one and check if element is correct, basic test
     */
    public function test_enumerate_from_start_only_one_stream()
    {
        $res = $this->concatenated_stream->enumerate(1);
        $this->assertSame($this->stream_element1, $res->get_elements()[0]->get_original_element());
    }

    /**
     * test enumerate from start of stream(cursor is null) and count not exceed the inventory of concatenatedStream
     */
    public function test_enumerate_from_start_multipe_stream()
    {
        $res = $this->concatenated_stream->enumerate(3);
        $this->assertSame(3, $res->get_size());
        $this->assertFalse($res->is_exhaustive());
    }

    /**
     * test when sum of streams elements count can't met the required count.
     */
    public function test_enumerate_from_start_multipe_stream_not_enough_elements()
    {
        $res = $this->concatenated_stream->enumerate(7);
        $this->assertSame(6, $res->get_size());
        $this->assertTrue($res->is_exhaustive());
    }

    /**
     * test when result of first enumeration is not all consumed, should start from the cursor position.
     */
    public function test_not_consume_all()
    {
        $res = $this->concatenated_stream->enumerate(4);
        $res = $this->concatenated_stream->enumerate(
            3,
            $res->get_elements()[0]->get_cursor()
                ->combine_with(
                    $res->get_elements()[1]->get_cursor()
                        ->combine_with($res->get_elements()[2]->get_cursor())
                )
        );
        $this->assertSame(3, $res->get_size());
        $this->assertSame($this->stream_element2, $res->get_elements()[0]->get_original_element());
        $this->assertTrue($res->is_exhaustive());
    }

    /**
     * test when result of first enumeration is only consumed one, should start from the cursor position.
     */
    public function test_not_consume_all_2()
    {
        $res = $this->concatenated_stream->enumerate(4);
        $res = $this->concatenated_stream->enumerate(5, $res->get_elements()[0]->get_cursor());
        $this->assertSame(5, $res->get_size());
        $this->assertSame($this->stream_element2, $res->get_elements()[0]->get_original_element());
        $this->assertTrue($res->is_exhaustive());
    }

    /**
     * @dataProvider multi_run_provider
     * @param int $first_round_count First round count.
     * @param int $second_round_count Second round count.
     * @param int $expected_count Expected count.
     * @param StreamElement $expected_element Expected element.
     * @param bool $expected_exhaust Exhausted ot nor.
     */
    public function test_multi_run($first_round_count, $second_round_count, $expected_count, StreamElement $expected_element, $expected_exhaust)
    {
        $res = $this->concatenated_stream->enumerate($first_round_count);
        $res = $this->concatenated_stream->enumerate($second_round_count, $res->get_combined_cursor());
        $this->assertSame($expected_count, $res->get_size());
        $this->assertSame($expected_element->get_debug_info(), $res->get_elements()[0]->get_original_element()->get_debug_info());
        $this->assertSame($expected_exhaust, $res->is_exhaustive());
    }

    /**
     * data provider for test_multi_run
     * @return array
     */
    public function multi_run_provider()
    {
        $this->setUp();
        return [
            'multi_run_1' => [3, 3, 3, $this->stream_element2, true],
            'multi_run_2' => [3, 2, 2, $this->stream_element2, false],
            'multi_run_3' => [3, 4, 3, $this->stream_element2, true],
            'multi_run_4' => [3, 2, 2, $this->stream_element2, false],
        ];
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $template = [
            '_type' => ConcatenatedStream::class,
            'streams' => [
                0 => [
                    '_type' => NullStream::class,
                ],
                1 => [
                    '_type' => NullStream::class,
                ],
            ],
            'stateful' => false,
        ];

        $s1 = new NullStream('amazing_null_stream');
        $s2 = new NullStream('another_amazing_null_stream');
        $concatenated = new ConcatenatedStream([$s1, $s2], 'amazing_concatenated_stream');
        $this->assertSame($template, $concatenated->to_template());

        return $template;
    }

    /**
     * @depends test_to_template
     * @param array $template The template.
     */
    public function test_from_template(array $template)
    {
        $context = new StreamContext($template, []);
        $this->assertSame($template, ConcatenatedStream::from_template($context)->to_template());
    }

    /**
     * Test get streams.
     */
    public function testGetStreams(): void
    {
        $stream = new ConcatenatedStream([new NullStream('null1'), new NullStream('null2')], 'awesome');
        $inner_streams = $stream->getStreams();
        $this->assertSame('null1', $inner_streams[0]->get_identity());
        $this->assertSame('null2', $inner_streams[1]->get_identity());
    }

    /**
     * Testing that when we call enumerate:
     *   - When the first inner stream returns less than the requested count,
     *     ConcatenatedStream will return those elements.
     *   - Pagination should take care of retrieving the following elements.
     *   - We don't continue to enumerate the following stream. We'll wait until the first one is exhausted.
     */
    public function testEnumerateWithPagination(): void
    {
        $this->stream_cursor1 = $this->getMockForAbstractClass(StreamCursor::class, ['awesome_cursor']);
        $this->stream_cursor2 = $this->getMockForAbstractClass(StreamCursor::class, ['awesome_cursor']);
        // mock stream element and passed in mocked stream cursor because StreamElement->get_cursor is final and can't be mocked.
        $this->stream_element1 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $this->stream_cursor1])->getMock();
        $this->stream_element2 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $this->stream_cursor1])->getMock();
        $this->stream_element3 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $this->stream_cursor2])->getMock();
        $this->stream_element4 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $this->stream_cursor2])->getMock();
        // mock element content
        $this->stream_element1->expects($this->any())->method('get_original_element')->willReturnSelf();
        $this->stream_element2->expects($this->any())->method('get_original_element')->willReturnSelf();
        $this->stream_element3->expects($this->any())->method('get_original_element')->willReturnSelf();
        $this->stream_element4->expects($this->any())->method('get_original_element')->willReturnSelf();
        // mock cursor's combine, and we only mock two cursors
        $this->stream_cursor1->expects($this->any())->method('_combine_with')->willReturnCallback(function ($other) {
            if ($other != $this->stream_cursor1) {
                return $other;
            } else {
                return $this->stream_cursor1;
            }
        });
        $this->stream_cursor2->expects($this->any())->method('_combine_with')->willReturnCallback(function ($other) {
            if ($other != $this->stream_cursor2) {
                return $this->stream_cursor2;
            } else {
                return $other;
            }
        });
        // always can combine for mocked cursor, not important here
        $this->stream_cursor1->expects($this->any())->method('_can_combine_with')->willReturn(true);
        $this->stream_cursor2->expects($this->any())->method('_can_combine_with')->willReturn(true);

        $this->stream1 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream1'])->getMock();
        /* _enumerate will be called until results are exhausted. further calls should be ignored. */
        $this->stream1->expects($this->any())->method('_enumerate')
            ->will($this->onConsecutiveCalls(
                new StreamResult(false, [$this->stream_element1]),
                new StreamResult(true, [$this->stream_element2]),
                new StreamResult(false, [$this->stream_element1]),
                new StreamResult(true, [$this->stream_element2])
            ));
        $this->stream2 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream2'])->getMock();
        $this->stream2->expects($this->any())->method('_enumerate')
            ->willReturn(new StreamResult(true, [$this->stream_element3, $this->stream_element4]));
        $this->concatenated_stream = new ConcatenatedStream([$this->stream1, $this->stream2], "concatenatedStream1");

        $result = $this->concatenated_stream->enumerate(4);
        $elements = $result->get_elements();
        $this->assertEquals(1, count($elements));
        $this->assertEquals($this->stream_element1->get_element_id(), $elements[0]->get_element_id());

        $result = $this->concatenated_stream->enumerate(4);
        $elements = $result->get_elements();
        $this->assertEquals(3, count($elements));
        $this->assertEquals($this->stream_element2->get_element_id(), $elements[0]->get_element_id());
        $this->assertEquals($this->stream_element3->get_element_id(), $elements[1]->get_element_id());
        $this->assertEquals($this->stream_element4->get_element_id(), $elements[2]->get_element_id());
    }

    /**
     * Test enumeration with ts state passed through EnumerationOption
     */
    public function testEnumerateWithStatefulPagination(): void
    {
        $el1_ts = 1632772460000;
        $el2_ts = 1632771460000;
        $el3_ts = 1632772360000;
        $el4_ts = 1632760460000;
        $elem1 = new MockedPostRefElement(1, 321, $el1_ts);
        $elem1->set_cursor($this->stream_cursor1);
        $elem2 = new MockedPostRefElement(2, 321, $el2_ts);
        $elem2->set_cursor($this->stream_cursor1);
        $elem3 = new MockedPostRefElement(3, 321, $el3_ts);
        $elem3->set_cursor($this->stream_cursor2);
        $elem4 = new MockedPostRefElement(4, 321, $el4_ts);
        $elem4->set_cursor($this->stream_cursor2);

        $stream1 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream1'])->getMock();
        $stream1->expects($this->any())->method('_enumerate')
            ->willReturn(new StreamResult(true, [$elem1, $elem2]));
        $stream2 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['stream2'])
            ->getMockForAbstractClass();
        $stream2->expects($this->once())
            ->method('_enumerate')
            ->with(1, null, null, new EnumerationOptions($el2_ts, null))
            ->willReturn(new StreamResult(true, [$elem4]));
        $stateful_concatenate = new ConcatenatedStream([$stream1, $stream2], 'test', true);

        $result = $stateful_concatenate->enumerate(3);
        $elements = $result->get_elements();
        $this->assertSame(3, count($elements));
        $this->assertSame($elem1->get_element_id(), $elements[0]->get_element_id());
        $this->assertSame($elem4->get_element_id(), $elements[2]->get_element_id());
    }

    /**
     * Test when individual stream failed, other streams should not be affected.
     */
    public function testIndividualStreamFailure(): void
    {
        // mock stream element and passed in mocked stream cursor because StreamElement->get_cursor is final and can't be mocked.
        $this->stream_element1 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['cool', $this->stream_cursor1])
            ->getMock();
        $this->stream_element2 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['cool', $this->stream_cursor1])
            ->getMock();
        // mock element content
        $this->stream_element1->expects($this->any())->method('get_original_element')->willReturnSelf();
        $this->stream_element2->expects($this->any())->method('get_original_element')->willReturnSelf();
        // mock cursor's combine, and we only mock two cursors
        $this->stream1 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['stream1'])
            ->getMockForAbstractClass();
        $this->stream1->expects($this->once())
            ->method('_enumerate')
            ->willThrowException(new \InvalidArgumentException('whatever'));
        $this->stream2 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['stream2'])
            ->getMock();
        $this->stream2
            ->expects($this->any())
            ->method('_enumerate')
            ->willReturn(new StreamResult(true, [$this->stream_element1, $this->stream_element2]));
        $this->concatenated_stream = new ConcatenatedStream([$this->stream1, $this->stream2], "concatenatedStream1");

        $result = $this->concatenated_stream->enumerate(4);
        $elements = $result->get_elements();
        $this->assertEquals(2, count($elements));
        $this->assertEquals($this->stream_element1->get_element_id(), $elements[0]->get_element_id());
        $this->assertEquals($this->stream_element2->get_element_id(), $elements[1]->get_element_id());
    }

    /**
     * Test setQueryString
     * @return void
     */
    public function testSetQueryString()
    {
        // stream 1 implements the method setQueryString
        $stream1 = $this->getMockBuilder(SizeLimitedStream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stream1->expects($this->once())->method('setQueryString');

        // stream 2 does not implement the method setQueryString
        $stream2 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream1'])->getMock();
        $concatenated_stream = new ConcatenatedStream([$stream1, $stream2], 'test');

        $concatenated_stream->setQueryString('tumblr university');
    }
}
