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

use Tumblr\StreamBuilder\InjectionAllocators\GlobalFixedInjectionAllocator;

/**
 * Class GlobalFixedInjectionAllocatorTest
 */
class GlobalFixedInjectionAllocatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider
     * @return array
     */
    public function data_provider_allocate()
    {
        return [
            [
                [
                    'positions' => [0, 10, 20, 30],
                    'page_size' => 10,
                    'res1' => [0],
                    'res2' => [0],
                    'res3' => [0],
                    'res4' => [0],
                ],
            ],
            [
                [
                    'positions' => [9, 19, 29, 39],
                    'page_size' => 10,
                    'res1' => [9],
                    'res2' => [9],
                    'res3' => [9],
                    'res4' => [9],
                ],
            ],
            [
                [
                    'positions' => [0, 30],
                    'page_size' => 10,
                    'res1' => [0],
                    'res2' => [],
                    'res3' => [],
                    'res4' => [0],
                ],
            ],
            [
                [
                    'positions' => [0, 1, 2, 3],
                    'page_size' => 10,
                    'res1' => [0, 1, 2, 3],
                    'res2' => [],
                    'res3' => [],
                    'res4' => [],
                ],
            ],
            [
                [
                    'positions' => [30, 31, 32, 33],
                    'page_size' => 10,
                    'res1' => [],
                    'res2' => [],
                    'res3' => [],
                    'res4' => [0, 1, 2, 3],
                ],
            ],
            [
                [
                    'positions' => [0, 10, 20, 30],
                    'page_size' => 0,
                    'res1' => [],
                    'res2' => [],
                    'res3' => [],
                    'res4' => [],
                ],
            ],
            [
                [
                    'positions' => [],
                    'page_size' => 10,
                    'res1' => [],
                    'res2' => [],
                    'res3' => [],
                    'res4' => [],
                ],
            ],
        ];
    }

    /**
     * @dataProvider data_provider_allocate
     * @param array $args Data provider arguments.
     * Test allocate
     */
    public function test_allocate(array $args)
    {
        $allocator = new GlobalFixedInjectionAllocator($args['positions']);
        $page_size = $args['page_size'];

        $res = $allocator->allocate($page_size);
        $this->assertSame($args['res1'], $res->get_allocate_output());

        $res = $allocator->allocate($page_size, $res->get_injector_state());
        $this->assertSame($args['res2'], $res->get_allocate_output());

        $res = $allocator->allocate($page_size, $res->get_injector_state());
        $this->assertSame($args['res3'], $res->get_allocate_output());

        $res = $allocator->allocate($page_size, $res->get_injector_state());
        $this->assertSame($args['res4'], $res->get_allocate_output());
    }
}
