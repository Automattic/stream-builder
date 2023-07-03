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

namespace Tests\Unit\Tumblr\StreamBuilder;

use Test\Mock\Tumblr\StreamBuilder\Interfaces\TestContextProvider;
use Tests\Mock\Tumblr\StreamBuilder\Interfaces\MockedCredentials;
use Tests\Mock\Tumblr\StreamBuilder\Interfaces\MockedLog;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\TransientCacheProvider;

class DependencyBagTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DependencyBag The bag.
     */
    private DependencyBag $bag;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->bag = self::retrieveDependencyBag();
    }

    /**
     * Test getLog.
     * @return void
     */
    public function testGetLog(): void
    {
        $this->assertInstanceOf(MockedLog::class, $this->bag->getLog());
    }

    /**
     * Test getCacheProvider.
     * @return void
     */
    public function testGetCacheProvider(): void
    {
        $this->assertInstanceOf(TransientCacheProvider::class, $this->bag->getCacheProvider());
    }

    /**
     * Test getCreds.
     * @return void
     */
    public function testGetCreds(): void
    {
        $this->assertInstanceOf(MockedCredentials::class, $this->bag->getCreds());
    }

    /**
     * Test getContextProvider.
     * @return void
     */
    public function testGetContextProvider(): void
    {
        $this->assertIsArray($this->bag->getContextProvider());
    }

    /**
     * Test getConfigDir.
     * @return void
     */
    public function testGetConfigDir(): void
    {
        $this->assertSame(CONFIG_DIR, $this->bag->getConfigDir());
    }

    /**
     * Test getBaseDir.
     * @return void
     */
    public function testGetBaseDir(): void
    {
        $this->assertSame(BASE_PATH, $this->bag->getBaseDir());
    }

    /**
     * Generate a dependency bag for testing.
     * @return DependencyBag
     */
    public static function retrieveDependencyBag(): DependencyBag
    {
        return new DependencyBag(
            new MockedLog(),
            new TransientCacheProvider(),
            new MockedCredentials(),
            new TestContextProvider()
        );
    }
}
