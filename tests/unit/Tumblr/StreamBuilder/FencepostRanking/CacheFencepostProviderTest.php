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

namespace Test\Tumblr\StreamBuilder\FencepostRanking;

use Test\Tumblr\StreamBuilder\StreamCursors\MockMaxCursor;
use Test\Tumblr\StreamBuilder\StreamElements\MockMaxStreamElement;
use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\FencepostRanking\CacheFencepostProvider;
use Tumblr\StreamBuilder\FencepostRanking\Fencepost;
use Tumblr\StreamBuilder\TransientCacheProvider;

/**
 * Test for CacheFencepostProvider
 */
class CacheFencepostProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var CacheFencepostProvider */
    private $fencepost_provider;
    /** @var CacheProvider */
    private $cache_provider;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->cache_provider = new TransientCacheProvider();
        $this->fencepost_provider = new CacheFencepostProvider($this->cache_provider);
    }

    /**
     * @return void
     */
    public function test_get_latest_timestamp__missing()
    {
        $this->assertNull($this->fencepost_provider->get_latest_timestamp('foo'));
    }

    /**
     * @return void
     */
    public function test_set_latest_timestamp__bad()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fencepost_provider->set_latest_timestamp('foo', -1);
    }

    /**
     * @return void
     */
    public function test_set_latest_timestamp__good()
    {
        $this->fencepost_provider->set_latest_timestamp('foo', 12345);
        $this->assertEquals(12345, $this->fencepost_provider->get_latest_timestamp('foo'));
    }

    /**
     * @return void
     */
    public function test_get_latest_fencepost__missing_timestamp()
    {
        $this->assertNull($this->fencepost_provider->get_latest_fencepost('foo'));
    }

    /**
     * @return void
     */
    public function test_get_latest_fencepost__missing_fencepost()
    {
        $this->fencepost_provider->set_latest_timestamp('foo', 12345);
        $this->assertNull($this->fencepost_provider->get_latest_fencepost('foo'));
    }

    /**
     * @return void
     */
    public function test_get_latest_fencepost__good()
    {
        $fencepost = new Fencepost([ new MockMaxStreamElement(0, 'wat', new MockMaxCursor(0)) ], new MockMaxCursor(1));
        $this->fencepost_provider->set_latest_timestamp('foo', 12345);
        $this->fencepost_provider->set_fencepost('foo', 12345, $fencepost);
        $this->assertEquals($fencepost, $this->fencepost_provider->get_latest_fencepost('foo'));
    }

    /**
     * @return void
     */
    public function test_get_fencepost__negative_timestamp()
    {
        $this->assertNull($this->fencepost_provider->get_fencepost('foo', -1));
    }

    /**
     * @return void
     */
    public function test_get_fencepost__missing()
    {
        $this->assertNull($this->fencepost_provider->get_fencepost('foo', 12345));
    }

    /**
     * @return void
     */
    public function test_set_fencepost__negative_timestamp()
    {
        $this->expectException(\InvalidArgumentException::class);
        $fencepost = new Fencepost([new MockMaxStreamElement(0, 'wat', new MockMaxCursor(0))], new MockMaxCursor(1));
        $this->fencepost_provider->set_fencepost('foo', -1, $fencepost);
    }

    /**
     * @return void
     */
    public function test_set_latest_fencepost__negative_timestamp()
    {
        $this->expectException(\InvalidArgumentException::class);
        $fencepost = new Fencepost([new MockMaxStreamElement(0, 'wat', new MockMaxCursor(0))], new MockMaxCursor(1));
        $this->fencepost_provider->set_latest_fencepost('foo', -1, $fencepost);
    }

    /**
     * @return void
     */
    public function test_set_latest_fencepost__good()
    {
        $fencepost = new Fencepost([new MockMaxStreamElement(0, 'wat', new MockMaxCursor(0))], new MockMaxCursor(1));
        $this->fencepost_provider->set_latest_fencepost('foo', 12345, $fencepost);
        $this->assertEquals(12345, $this->fencepost_provider->get_latest_timestamp('foo'));
        $this->assertEquals($fencepost, $this->fencepost_provider->get_fencepost('foo', 12345));
        $this->assertEquals($fencepost, $this->fencepost_provider->get_latest_fencepost('foo'));
    }

    /**
     * @return void
     */
    public function test_set_get_fencepost_epoch()
    {
        $null_epoch = $this->fencepost_provider->get_fencepost_epoch('1234');
        $this->fencepost_provider->set_fencepost_epoch('1234', 9874564560);
        $epoch = $this->fencepost_provider->get_fencepost_epoch('1234');
        $this->assertNull($null_epoch);
        $this->assertEquals(9874564560, $epoch);
        $this->fencepost_provider->set_fencepost_epoch('1234', 9874564570);
        $this->assertEquals(9874564570, $this->fencepost_provider->get_fencepost_epoch('1234'));
    }

    /**
     * @return void
     */
    public function test_set_delete_get_fencepost_epoch()
    {
        $null_epoch = $this->fencepost_provider->get_fencepost_epoch('1234');
        $this->fencepost_provider->set_fencepost_epoch('1234', 9874564560);
        $epoch = $this->fencepost_provider->get_fencepost_epoch('1234');
        $this->assertNull($null_epoch);
        $this->assertSame(9874564560, $epoch);
        $this->fencepost_provider->delete_fencepost_epoch('1234');
        $this->assertNull($this->fencepost_provider->get_fencepost_epoch('1234'));
    }
}
