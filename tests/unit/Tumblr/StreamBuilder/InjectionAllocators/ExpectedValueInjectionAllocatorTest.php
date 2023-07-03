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

use Tumblr\StreamBuilder\InjectionAllocators\ExpectedValueInjectionAllocator;
use Tumblr\StreamBuilder\StreamContext;

/**
 * Class ExpectedValueInjectionAllocatorTest
 */
class ExpectedValueInjectionAllocatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $allocator = new ExpectedValueInjectionAllocator(0.25);
        $this->assertSame([
            '_type' => ExpectedValueInjectionAllocator::class,
            'slot_injection_probability' => 0.25,
        ], $allocator->to_template());
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => ExpectedValueInjectionAllocator::class,
            'slot_injection_probability' => 0.25,
        ];
        $allocator = ExpectedValueInjectionAllocator::from_template(new StreamContext($template, []));
        $this->assertSame($allocator->to_template(), $template);
    }

    /**
     * Test Allocate
     */
    public function test_allocate()
    {
        $allocator = new ExpectedValueInjectionAllocator(1);
        $this->assertSame([0, 1, 2], $allocator->allocate(3)->get_allocate_output());

        $allocator = new ExpectedValueInjectionAllocator(0.099);
        $this->assertGreaterThanOrEqual(0, $allocator->allocate(10)->get_allocate_output_count());
        $this->assertLessThanOrEqual(10, $allocator->allocate(10)->get_allocate_output_count());
    }
}
