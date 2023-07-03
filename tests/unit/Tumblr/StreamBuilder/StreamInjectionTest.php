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

use Tumblr\StreamBuilder\StreamInjection;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;

/**
 * Class StreamInjectionTest
 */
class StreamInjectionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * To test get_injector
     */
    public function test_get_injector()
    {
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();

        /** @var StreamInjection $stream_injection */
        $stream_injection = $this->getMockBuilder(StreamInjection::class)
            ->setConstructorArgs([$injector])
            ->getMock();

        $this->assertSame(
            $injector,
            $stream_injection->get_injector()
        );
    }
}
