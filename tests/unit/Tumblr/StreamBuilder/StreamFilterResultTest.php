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

use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;

/**
 * Class StreamFilterResultTest
 */
class StreamFilterResultTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Provide invalid construct data.
     * @return array
     */
    public function invalid_construct_provider()
    {
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        return [
            [[123], [$el]],
            [[$el], [null]],
            [['bar'], []],
            [[], ['foo']],
        ];
    }

    /**
     * Test __construct failure exceptions.
     * @param array $retained Retained array
     * @param array $released Released array
     * @return void
     * @dataProvider invalid_construct_provider
     */
    public function test_construct_failure(array $retained, array $released)
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new StreamFilterResult($retained, $released);
    }

    /**
     * Test create_empty
     * @return void
     */
    public function test_create_empty()
    {
        $this->assertEquals(new StreamFilterResult([], []), StreamFilterResult::create_empty());
    }

    /**
     * Test get_filter_states
     */
    public function test_get_filter_states()
    {
        $state = $this->getMockBuilder(StreamFilterState::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $result = new StreamFilterResult([], [], [
            'bar' => $state,
        ]);
        $this->assertSame($result->get_filter_states(), [
            'bar' => $state,
        ]);
    }
}
