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

use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Class LeafStreamElementTest
 */
class LeafStreamElementTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test get_original_element
     */
    public function test_get_original_element()
    {
        /** @var LeafStreamElement $leaf_se */
        $leaf_se = $this->getMockBuilder(LeafStreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->assertSame($leaf_se, $leaf_se->get_original_element());
    }

    /**
     * Test add_debug_info and get_debug_info
     */
    public function test_debug_info()
    {
        /** @var StreamElement $el */
        $el = $this->getMockBuilder(LeafStreamElement::class)->setConstructorArgs(['cool'])->getMockForAbstractClass();

        $el->add_debug_info('foo', 'bar', '123');
        $this->assertSame([
            'foo' => [
                'bar' => '123',
            ],
        ], $el->get_debug_info());

        $el->add_debug_info('foo', 'bar', '122'); // The current scalar type hint will actually convert int to string.
        $this->assertSame([
            'foo' => [
                'bar' => '122',
            ],
        ], $el->get_debug_info());

        $el->add_debug_info('foo', 'baz', '222');
        $this->assertSame([
            'foo' => [
                'bar' => '122',
                'baz' => '222',
            ],
        ], $el->get_debug_info());
    }
}
