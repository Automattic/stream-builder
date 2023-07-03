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
 * Class UnmergeableFilterStateExceptionTest
 */
class UnmergeableFilterStateExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor.
     */
    public function test_constructor()
    {
        /** @var StreamFilterState|\PHPUnit\Framework\MockObject\MockObject $base_state */
        $base_state = $this->getMockBuilder(StreamFilterState::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $base_state->expects($this->once())
            ->method('to_string')
            ->willReturn('amazing_base_state');

        /** @var StreamFilterState|\PHPUnit\Framework\MockObject\MockObject $another_state */
        $another_state = $this->getMockBuilder(StreamFilterState::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $another_state->expects($this->once())
            ->method('to_string')
            ->willReturn('goofy_another_state');

        $exception = new UnmergeableFilterStateException($base_state, $another_state);
        $message = '/Incompatible filter states: \'amazing_base_state\' \(Mock_StreamFilterState_[a-z0-9]{8}\) ' .
            'cannot merge with \'goofy_another_state\' \(Mock_StreamFilterState_[a-z0-9]{8}\)/';
        $this->assertMatchesRegularExpression($message, $exception->getMessage());
    }
}
