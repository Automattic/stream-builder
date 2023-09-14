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

namespace Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\Exceptions\MissingCacheException;

/**
 * Override the time() method call in TransientCacheProvider.
 * @return int
 */
function time()
{
    return TransientCacheProviderTest::$now ?: \time();
}

/**
 * Tests for the TransientCacheProvider
 */
class TransientCacheProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var int $now Timestamp that will be returned by time()
     */
    public static $now;

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        static::$now = null;
    }

    /**
     * Make sure missing keys are indeed missing using get.
     * @return void
     */
    public function test_get_missing()
    {
        $tcp = new TransientCacheProvider();
        $this->assertNull($tcp->get(CacheProvider::OBJECT_TYPE_FILTER, 'bar'));
        $this->assertNull($tcp->get(CacheProvider::OBJECT_TYPE_FILTER, 'bar')); // make sure getting it does not create it!
    }

    /**
     * Make sure missing keys are indeed missing using get_multi.
     * @return void
     */
    public function test_get_multi_missing()
    {
        $tcp = new TransientCacheProvider();
        $missing = [ 'dummy' ];
        $this->assertEmpty($tcp->get_multi(CacheProvider::OBJECT_TYPE_FILTER, ['bar', 'baz'], $missing));
        $this->assertSame(['bar' => 'bar', 'baz' => 'baz'], $missing);
    }

    /**
     * Make sure duplicate missing keys are collapsed.
     * @return void
     */
    public function test_get_multi_duplicate_missing()
    {
        $tcp = new TransientCacheProvider();
        $missing = [ 'dummy' ];
        $this->assertEmpty($tcp->get_multi(CacheProvider::OBJECT_TYPE_FILTER, ['bar', 'bar', 'bar'], $missing));
        $this->assertSame(['bar' => 'bar'], $missing);
    }

    /**
     * Test TTL works. This test is "slow" according to PHPUnit, but that is by design.
     * @return void
     *
     * @group slow
     */
    public function test_ttl()
    {
        $tcp = new TransientCacheProvider();
        $tcp->set(CacheProvider::OBJECT_TYPE_FILTER, 'bar', 'baz', 1);
        static::$now = \time() - 1; // go backwards in time
        $this->assertSame('baz', $tcp->get(CacheProvider::OBJECT_TYPE_FILTER, 'bar'));
        static::$now = \time() + 10; // go forwards in time
        $this->assertNull($tcp->get(CacheProvider::OBJECT_TYPE_FILTER, 'bar'));
    }

    /**
     * Test composite operations
     * @return void
     */
    public function test_multi_ops()
    {
        $tcp = new TransientCacheProvider();
        $tcp->set(CacheProvider::OBJECT_TYPE_FILTER, 'bar1', 'a');
        $tcp->set(CacheProvider::OBJECT_TYPE_FILTER, 'bar2', 'b');
        $tcp->set(CacheProvider::OBJECT_TYPE_FILTER, 'bar3', 'c');
        $tcp->set_multi(CacheProvider::OBJECT_TYPE_FILTER, [
            'bar3' => 'x',
            'bar4' => 'y',
            'bar5' => 'z',
        ]);
        $missing = [ 'dummy' ];
        $this->assertSame([
            'bar1' => 'a',
            'bar2' => 'b',
            'bar3' => 'x',
            'bar4' => 'y',
            'bar5' => 'z',
        ], $tcp->get_multi(CacheProvider::OBJECT_TYPE_FILTER, ['bar0', 'bar1', 'bar2', 'bar3', 'bar4', 'bar5', 'bar6'], $missing));
        $this->assertSame([
            'bar0' => 'bar0',
            'bar6' => 'bar6',
        ], $missing);
    }

    /**
     * Test that different ache names are isolated.
     * @return void
     */
    public function test_isolation()
    {
        $tcp = new TransientCacheProvider();
        $tcp->set(CacheProvider::OBJECT_TYPE_FILTER, 'foo', 'a');
        $tcp->set(CacheProvider::OBJECT_TYPE_FILTER, 'bar', 'b');
        $tcp->set(CacheProvider::OBJECT_TYPE_CURSOR, 'foo', 'c');
        $tcp->set(CacheProvider::OBJECT_TYPE_CURSOR, 'bar', 'd');

        $m1 = [ 'dummy' ];
        $this->assertSame(['foo' => 'a', 'bar' => 'b'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_FILTER, ['foo', 'bar']));
        $this->assertSame(['foo' => 'a', 'bar' => 'b'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_FILTER, ['foo', 'bar'], $m1));
        $this->assertEmpty($m1);
        $this->assertSame(['foo' => 'a', 'bar' => 'b'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_FILTER, ['foo', 'bar', 'baz']));
        $this->assertSame(['foo' => 'a', 'bar' => 'b'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_FILTER, ['foo', 'bar', 'baz'], $m1));
        $this->assertSame(['baz' => 'baz'], $m1);

        $m2 = [ 'dummy' ];
        $this->assertSame(['foo' => 'c', 'bar' => 'd'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_CURSOR, ['foo', 'bar']));
        $this->assertSame(['foo' => 'c', 'bar' => 'd'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_CURSOR, ['foo', 'bar'], $m2));
        $this->assertEmpty($m2);
        $this->assertSame(['foo' => 'c', 'bar' => 'd'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_CURSOR, ['foo', 'bar', 'baz']));
        $this->assertSame(['foo' => 'c', 'bar' => 'd'], $tcp->get_multi(CacheProvider::OBJECT_TYPE_CURSOR, ['foo', 'bar', 'baz'], $m2));
        $this->assertSame(['baz' => 'baz'], $m2);
    }

    /**
     * Make sure keys are properly deleted.
     * @return void
     */
    public function test_delete()
    {
        $tcp = new TransientCacheProvider();
        $tcp->set(CacheProvider::OBJECT_TYPE_FILTER, 'foo', 'a');
        $this->assertSame('a', $tcp->get(CacheProvider::OBJECT_TYPE_FILTER, 'foo'));
        $tcp->delete(CacheProvider::OBJECT_TYPE_FILTER, 'foo');
        $this->assertNull($tcp->get(CacheProvider::OBJECT_TYPE_FILTER, 'foo'));

        $this->expectException(MissingCacheException::class);
        $tcp->delete(CacheProvider::OBJECT_TYPE_FILTER, 'foo');
    }
}
