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

use Tumblr\StreamBuilder\InjectionAllocators\LocalFixedInjectionAllocator;
use Tumblr\StreamBuilder\StreamContext;

/**
 * Class LocalFixedInjectionAllocatorTest
 */
class LocalFixedInjectionAllocatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $allocator = new LocalFixedInjectionAllocator([3, 5, 10]);
        $this->assertSame([
            '_type' => LocalFixedInjectionAllocator::class,
            'positions' => [3, 5, 10],
        ], $allocator->to_template());
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => LocalFixedInjectionAllocator::class,
            'positions' => [3, 5, 10],
        ];
        $allocator = LocalFixedInjectionAllocator::from_template(new StreamContext($template, []));
        $this->assertSame($allocator->to_template(), $template);
    }

    /**
     * Test allocate
     */
    public function test_allocate()
    {
        $allocator = new LocalFixedInjectionAllocator([3, 5, 10]);
        $this->assertSame([3, 5, 10], $allocator->allocate(12)->get_allocate_output());
        $this->assertSame([3, 5], $allocator->allocate(10)->get_allocate_output());
    }
}
