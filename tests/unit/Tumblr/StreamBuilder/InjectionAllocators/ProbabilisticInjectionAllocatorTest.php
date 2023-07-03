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

use Tumblr\StreamBuilder\InjectionAllocators\ProbabilisticInjectionAllocator;
use Tumblr\StreamBuilder\StreamContext;

/**
 * Class ProbabilisticInjectionAllocatorTest
 */
class ProbabilisticInjectionAllocatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $allocator = new ProbabilisticInjectionAllocator(0.5);
        $this->assertSame([
            '_type' => ProbabilisticInjectionAllocator::class,
            'slot_injection_probability' => 0.5,
        ], $allocator->to_template());

        $allocator = new ProbabilisticInjectionAllocator(1.5);
        $this->assertSame([
            '_type' => ProbabilisticInjectionAllocator::class,
            'slot_injection_probability' => 1.0,
        ], $allocator->to_template());

        $allocator = new ProbabilisticInjectionAllocator(-0.5);
        $this->assertSame([
            '_type' => ProbabilisticInjectionAllocator::class,
            'slot_injection_probability' => 0.0,
        ], $allocator->to_template());
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => ProbabilisticInjectionAllocator::class,
            'slot_injection_probability' => 0.5,
        ];
        $allocator = ProbabilisticInjectionAllocator::from_template(new StreamContext($template, []));
        $this->assertSame($allocator->to_template(), $template);
    }

    /**
     * Test allocate
     */
    public function test_allocate()
    {
        $allocator = new ProbabilisticInjectionAllocator(1);
        $this->assertSame(10, $allocator->allocate(10)->get_allocate_output_count());

        $allocator = new ProbabilisticInjectionAllocator(0.25);
        $this->assertGreaterThanOrEqual(0, $allocator->allocate(3)->get_allocate_output_count());
        $this->assertLessThanOrEqual(3, $allocator->allocate(3)->get_allocate_output_count());
    }
}
