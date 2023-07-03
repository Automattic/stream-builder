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

/**
 * Class NullCacheProviderTest
 */
class NullCacheProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test get
     */
    public function test_get()
    {
        $provider = new NullCacheProvider();
        $this->assertNull($provider->get(123, 'whatever'));
    }

    /**
     * Test get_multi
     */
    public function test_get_multi()
    {
        $provider = new NullCacheProvider();
        $keys = ['foo', 'bar'];
        $not_found = [];
        $this->assertSame([], $provider->get_multi(123, $keys, $not_found));
        $this->assertSame($keys, $not_found);
    }

    /**
     * Test set and set_multi
     */
    public function test_set_ant_set_multi()
    {
        $provider = new NullCacheProvider();
        $provider->set(123, 'whatever', 'yay');
        $result = $provider->set_multi(123, ['whatever' => 'yay']);
        $this->assertNull($result);
    }
}
