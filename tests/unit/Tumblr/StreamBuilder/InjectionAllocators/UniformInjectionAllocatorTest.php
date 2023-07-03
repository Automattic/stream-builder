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

use Tumblr\StreamBuilder\InjectionAllocators\UniformInjectionAllocator;
use Tumblr\StreamBuilder\StreamContext;

/**
 * Class UniformInjectionAllocatorTest
 */
class UniformInjectionAllocatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     * @return void
     */
    public function test_to_template()
    {
        $allocator = new UniformInjectionAllocator(5, 2);
        $this->assertSame([
            '_type' => UniformInjectionAllocator::class,
            'modulus' => 5,
            'remainder' => 2,
        ], $allocator->to_template());
    }

    /**
     * Test from_template
     * @return void
     */
    public function test_from_template()
    {
        $template = [
            '_type' => UniformInjectionAllocator::class,
            'modulus' => 5,
            'remainder' => 2,
        ];
        $allocator = UniformInjectionAllocator::from_template(new StreamContext($template, []));
        $this->assertSame($allocator->to_template(), $template);
    }

    /**
     * Test constructor failure (bad types)
     * @return void
     */
    public function test_constructor_failure_bad_type()
    {
        $this->expectException(\TypeError::class);
        new UniformInjectionAllocator('foo', 2);
    }

    /**
     * Test constructor failure (bad modulus)
     * @return void
     */
    public function test_constructor_failure_bad_modulus()
    {
        $this->expectException(\InvalidArgumentException::class);
        new UniformInjectionAllocator(1, 2);
    }

    /**
     * Test allocate
     * @return void
     */
    public function test_allocate()
    {
        $allocator = new UniformInjectionAllocator(5, 2);
        $this->assertSame([2, 7], $allocator->allocate(12)->get_allocate_output());
        $this->assertSame([], $allocator->allocate(2)->get_allocate_output());

        $state = [];
        $result = $allocator->allocate(12, $state);
        $this->assertSame([2, 7], $result->get_allocate_output());
        $this->assertSame(['next_offset' => 0], $result->get_injector_state());

        $result = $allocator->allocate(12, $result->get_injector_state());
        $this->assertSame([0, 5, 10], $result->get_allocate_output());
        $this->assertSame(['next_offset' => 3], $result->get_injector_state());

        $result = $allocator->allocate(12, $result->get_injector_state());
        $this->assertSame([3, 8], $result->get_allocate_output());
        $this->assertSame(['next_offset' => 1], $result->get_injector_state());

        $result = $allocator->allocate(12, $result->get_injector_state());
        $this->assertSame([1, 6, 11], $result->get_allocate_output());
    }

    /**
     * Test allocate modulus large than page size case.
     * @return void
     */
    public function test_allocate_large_modulus()
    {
        $allocator = new UniformInjectionAllocator(30, 2);

        $state = [];
        $result = $allocator->allocate(12, $state);
        $this->assertSame([2], $result->get_allocate_output());
        $this->assertSame(['next_offset' => 20], $result->get_injector_state());

        $result = $allocator->allocate(12, $result->get_injector_state());
        $this->assertSame([], $result->get_allocate_output());
        $this->assertSame(['next_offset' => 8], $result->get_injector_state());

        $result = $allocator->allocate(12, $result->get_injector_state());
        $this->assertSame([8], $result->get_allocate_output());
        $this->assertSame(['next_offset' => 26], $result->get_injector_state());
    }
}
