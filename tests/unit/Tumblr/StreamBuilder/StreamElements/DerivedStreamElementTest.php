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
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class DerivedStreamElementTest
 */
class DerivedStreamElementTest extends \PHPUnit\Framework\TestCase
{
    /** @var StreamElement|null */
    protected $se = null;
    /** @var Stream|null */
    protected $stream = null;
    /** @var StreamCursor|null */
    protected $sc = null;

    /**
     * Setup
     * @return void
     */
    protected function setUp(): void
    {
        $this->se = $this->getMockBuilder(LeafStreamElement::class)
            ->setConstructorArgs(['awesome_id', null, 'amazing_id'])
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $this->se->expects($this->any())
            ->method('to_template')
            ->willReturn(['_type' => 'dummy']);
        $this->se->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('leaf_stream_element_key');
        $this->stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['awesome_id'])
            ->getMock();
        $this->sc = $this
            ->getMockBuilder(StreamCursor::class)
            ->setConstructorArgs(['awesome_id'])
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $this->sc->expects($this->any())->method('to_template')->willReturn([ 'cool_yo' ]);
    }

    /**
     * Test get_original_element
     * @return void
     */
    public function test_get_original_element()
    {
        $derived_se = new DerivedStreamElement($this->se, $this->stream->get_identity(), $this->sc);
        $this->assertSame($this->se, $derived_se->get_original_element());
    }

    /**
     * Test get_cache_key
     * @return void
     */
    public function test_get_cache_key()
    {
        $derived_se = new DerivedStreamElement($this->se, $this->stream->get_identity(), $this->sc);
        $this->assertSame('leaf_stream_element_key', $derived_se->get_cache_key());
    }

    /**
     * Test add_debug_info and get_debug_info
     */
    public function test_debug_info()
    {
        /** @var LeafStreamElement $parent */
        $parent = $this->getMockBuilder(LeafStreamElement::class)->setConstructorArgs(['cool1'])->getMockForAbstractClass();
        /** @var DerivedStreamElement $element */
        $element = $this->getMockBuilder(DerivedStreamElement::class)
            ->setConstructorArgs([$parent, 'cool2'])
            ->getMockForAbstractClass();

        $element->add_debug_info('foo', 'bar', '123');
        $expected = [
            'foo' => [
                'bar' => '123',
            ],
        ];
        $this->assertSame($expected, $element->get_debug_info());
        $this->assertSame($expected, $parent->get_debug_info());

        // The current scalar type hint will actually convert int to string.
        $element->add_debug_info('foo', 'bar', '122');
        $expected = [
            'foo' => [
                'bar' => '122',
            ],
        ];
        $this->assertSame($expected, $element->get_debug_info());
        $this->assertSame($expected, $parent->get_debug_info());

        $element->add_debug_info('foo', 'baz', '222');
        $expected = [
            'foo' => [
                'bar' => '122',
                'baz' => '222',
            ],
        ];
        $this->assertSame($expected, $element->get_debug_info());
        $this->assertSame($expected, $parent->get_debug_info());
    }

    /**
     * Test to_template
     * @return void
     */
    public function test_to_template()
    {
        $derived_se = new DerivedStreamElement($this->se, $this->stream->get_identity(), $this->sc);
        $this->assertSame([
            '_type' => DerivedStreamElement::class,
            'provider_id' => 'awesome_id',
            'cursor' => [ 'cool_yo' ],
            'element_id' => 'amazing_id',
            'parent' => [ '_type' => 'dummy' ],
        ], $derived_se->to_template());
    }

    /**
     * Test from template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => DerivedStreamElement::class,
            'provider_id' => 'awesome_id',
            'cursor' => [
                '_type' => MockMaxCursor::class,
                'max' => 456,
            ],
            'element_id' => 'amazing_id',
            'parent' => [
                '_type' => MockMaxStreamElement::class,
                'provider_id' => 'awesome_parent',
                'cursor' => [
                    '_type' => MockMaxCursor::class,
                    'max' => 789,
                ],
                'element_id' => 'amazing_id',
                'value' => 123,
            ],
        ];
        $context = new StreamContext($template, []);
        $this->assertSame(
            $template,
            DerivedStreamElement::from_template($context)->to_template()
        );
    }
}
