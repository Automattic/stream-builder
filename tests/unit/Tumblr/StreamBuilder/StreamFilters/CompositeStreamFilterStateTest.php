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

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilterState;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamFilterState;

/**
 * Class CompositeStreamFilterStateTest
 */
class CompositeStreamFilterStateTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor with exception
     */
    public function test_constructor_exception()
    {
        $this->expectException(TypeMismatchException::class);
        new CompositeStreamFilterState(['amazing_id' => 'invalid_state']);
    }

    /**
     * Test state_for_filter
     */
    public function test_state_for_filter()
    {
        /** @var CompositeStreamFilterState|\PHPUnit\Framework\MockObject\MockObject $state */
        $state = $this->getMockBuilder(StreamFilterState::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        /** @var StreamFilter|\PHPUnit\Framework\MockObject\MockObject $sf */
        $sf = $this->getMockBuilder(StreamFilter::class)
            ->setConstructorArgs(['ello'])
            ->setMethods(['get_state_id'])
            ->getMockForAbstractClass();
        $sf->expects($this->exactly(2))
            ->method('get_state_id')
            ->willReturn('amazing_state_id');

        $composite_state = new CompositeStreamFilterState([
            'stupid_state_id' => $state,
        ]);
        $this->assertNull($composite_state->state_for_filter($sf));

        $composite_state = new CompositeStreamFilterState([
            'amazing_state_id' => $state,
        ]);
        $this->assertSame($state, $composite_state->state_for_filter($sf));
    }
}
