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

use Tumblr\StreamBuilder\NullCacheProvider;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamFilters\DeduplicatedStreamFilterState;

/**
 * Class DeduplicatedStreamFilterStateTest
 */
class DeduplicatedStreamFilterStateTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return array
     */
    public function provider_constructor_exception()
    {
        return [
            ['bar', \TypeError::class],
            [-1, \InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider provider_constructor_exception
     * @param mixed $window Window
     * @param string $exception Exception
     */
    public function test_constructor_failure($window, string $exception)
    {
        $this->expectException($exception);
        new DeduplicatedStreamFilterState($window, [], new NullCacheProvider());
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            'i' => 'foo.bar.baz',
        ];
        $context = new StreamContext($template, []);

        $state = DeduplicatedStreamFilterState::from_template($context);
        $this->assertSame([
            0 => 'foo',
            1 => 'bar',
            2 => 'baz',
        ], $state->get_seen_items());
    }
}
