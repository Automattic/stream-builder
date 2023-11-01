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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class StreamResultTest
 */
class StreamResultTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array
     */
    protected $stream_elements = [];

    /**
     * Set up
     */
    protected function setUp(): void
    {
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $el1 */
        $el1 = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $el2 */
        $el2 = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->stream_elements = [$el1, $el2];
    }

    /**
     * Test constructor_failure
     */
    public function test_constructor_failure()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new StreamResult(false, [new \stdClass()]);
    }

    /**
     * Test get_elements
     */
    public function test_get_elements()
    {
        $stream_result = new StreamResult(false, $this->stream_elements);
        $got_elements = $stream_result->get_elements();
		$element_count = is_countable($got_elements) ? count($got_elements) : 0;
        $this->assertSame(2, $element_count);
    }

    /**
     * Test get_original_elements
     */
    public function test_get_original_elements()
    {
        /** @var StreamElement $el1 */
        $el1 = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $el2 = new DerivedStreamElement($el1, 'tester');
        $result = new StreamResult(false, [$el2]);
        $elements = $result->get_original_elements();
		$element_count = is_countable($elements) ? count($elements) : 0;
        $this->assertSame(1, $element_count);
        $this->assertSame(DerivedStreamElement::class, get_class($result->get_element_at_index(0)));
        $this->assertStringStartsWith('Mock_StreamElement_', get_class($elements[0]));

        $result = new StreamResult(false, []);
        $elements = $result->get_original_elements();
        $this->assertEmpty($elements);
    }

    /**
     * Test is_exhaustive
     */
    public function test_is_exhaustive()
    {
        $stream_result = new StreamResult(false, $this->stream_elements);
        $is_exhaustive = $stream_result->is_exhaustive();
        $this->assertFalse($is_exhaustive);
    }

    /**
     * Test get_size
     */
    public function test_get_size()
    {
        $stream_result = new StreamResult(false, $this->stream_elements);
        $size = $stream_result->get_size();
        $this->assertSame(2, $size);
    }

    /**
     * Test get_combined_cursor
     */
    public function test_get_combined_cursor()
    {
        $cur1 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cur2 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cur3 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cur2->expects($this->once())
            ->method('_can_combine_with')
            ->willReturn(true);
        $cur2->expects($this->once())
            ->method('_combine_with')
            ->willReturn($cur3);

        $el1 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool1', $cur1])->getMockForAbstractClass();
        $el2 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool2', $cur2])->getMockForAbstractClass();

        $stream_result = new StreamResult(false, [$el1, $el2]);
        $combined_cursor = $stream_result->get_combined_cursor();
        $this->assertSame($cur3, $combined_cursor);
    }

    /**
     * Test get_combined_cursor with until input.
     */
    public function test_get_combined_cursor_with_until()
    {
        $cur1 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cur2 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cur3 = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $combined = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cur2->expects($this->once())
            ->method('_can_combine_with')
            ->willReturn(true);
        $cur2->expects($this->once())
            ->method('_combine_with')
            ->willReturn($combined);
        $cur3->expects($this->never())
            ->method('_can_combine_with');

        $el1 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool1', $cur1])->getMockForAbstractClass();
        $el2 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool2', $cur2])->getMockForAbstractClass();
        $el3 = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool2', $cur3])->getMockForAbstractClass();

        $stream_result = new StreamResult(false, [$el1, $el2, $el3]);
        $combined_cursor = $stream_result->get_combined_cursor(2);
        $this->assertSame($combined, $combined_cursor);
    }

    /**
     * Test prepend
     */
    public function test_prepend()
    {
        $el3 = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stream_result = new StreamResult(false, $this->stream_elements);
        $new_stream_result = StreamResult::prepend([$el3], $stream_result);
        $this->assertFalse($new_stream_result->is_exhaustive());
        $this->assertSame(3, $new_stream_result->get_size());
    }

    /**
     * Test derive_all
     */
    public function test_derive_all()
    {
        /** @var Stream $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['awesome_stream'])
            ->getMock();
        $stream_result = new StreamResult(false, $this->stream_elements);
        $new_stream_result = $stream_result->derive_all($stream);

        $this->assertSame(2, $new_stream_result->get_size());

        /** @var DerivedStreamElement $first_el */
        $first_el = $new_stream_result->get_elements()[0];
        $this->assertInstanceOf(
            DerivedStreamElement::class,
            $first_el
        );
        $this->assertSame(
            'awesome_stream',
            $first_el->get_provider_identity()
        );
        $this->assertNull($first_el->get_cursor());
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $template = [
            '_type' => StreamResult::class,
            'is_exhaustive' => false,
            'elements' => [
                0 => [
                    '_type' => MockMaxStreamElement::class,
                    'provider_id' => 'p1',
                    'cursor' => [
                        '_type' => MockMaxCursor::class,
                        'max' => 1,
                    ],
                    'element_id' => 'amazing_id_1',
                    'value' => 1,
                ],
                1 => [
                    '_type' => MockMaxStreamElement::class,
                    'provider_id' => 'p2',
                    'cursor' => [
                        '_type' => MockMaxCursor::class,
                        'max' => 2,
                    ],
                    'element_id' => 'amazing_id_2',
                    'value' => 2,
                ],
            ],
        ];

        $e1 = new MockMaxStreamElement(1, 'p1', new MockMaxCursor(1), 'amazing_id_1');
        $e2 = new MockMaxStreamElement(2, 'p2', new MockMaxCursor(2), 'amazing_id_2');
        $result = new StreamResult(false, [$e1, $e2]);
        $this->assertSame($template, $result->to_template());

        return $template;
    }

    /**
     * @depends test_to_template
     * @param array $template The template.
     */
    public function test_from_template(array $template)
    {
        $context = new StreamContext($template, []);
        $this->assertSame($template, StreamResult::from_template($context)->to_template());
    }
}
