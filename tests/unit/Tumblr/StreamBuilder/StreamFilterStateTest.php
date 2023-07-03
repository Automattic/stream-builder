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

use Tumblr\StreamBuilder\Exceptions\UnmergeableFilterStateException;
use Tumblr\StreamBuilder\StreamFilterState;

/**
 * Class StreamFilterStateTest
 */
class StreamFilterStateTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test merge_with
     */
    public function test_merge_with()
    {
        /** @var StreamFilterState|\PHPUnit\Framework\MockObject\MockObject $state */
        $state = $this->getMockBuilder(StreamFilterState::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $state->expects($this->once())
            ->method('_can_merge_with')
            ->willReturn(false);
        $state->expects($this->once())
            ->method('to_string')
            ->willReturn('amazing_state');

        $another_state = $this->getMockBuilder(StreamFilterState::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $another_state->expects($this->once())
            ->method('to_string')
            ->willReturn('another_amazing_state');

        $this->expectException(UnmergeableFilterStateException::class);
        $state->merge_with($another_state);
    }
}
