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

use ReflectionClass;
use Test\Mock\Tumblr\StreamBuilder\Interfaces\TestContextProvider;
use Tests\Mock\Tumblr\StreamBuilder\Interfaces\MockedCredentials;
use Tests\Mock\Tumblr\StreamBuilder\Interfaces\MockedLog;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\TransientCacheProvider;

class StreamBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DependencyBag The dependency bag.
     */
    private DependencyBag $dependency_bag;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dependency_bag = $this->createMock(DependencyBag::class);
        // We already bootstrap StreamBuilder, so we need unset it.
        self::overrideStreamBuilderInit(null);
    }

    /**
     * Redo the dependency bag injection.
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $dependency_bag = new DependencyBag(
            new MockedLog(),
            new TransientCacheProvider(),
            new MockedCredentials(),
            new TestContextProvider()
        );
        StreamBuilderTest::overrideStreamBuilderInit($dependency_bag);
    }


    /**
     * Test init() and getDependencyBag() methods.
     * @return void
     */
    public function testSetDependencyBagWithFieldNotSetWorks()
    {
        StreamBuilder::init($this->dependency_bag);
        $this->assertSame($this->dependency_bag, StreamBuilder::getDependencyBag());
    }

    /**
     * Test running init() method twice throws an exception.
     * @return void
     */
    public function testSetDependencyBagWithFieldSetThrowsRuntimeException()
    {
        StreamBuilder::init($this->dependency_bag);

        $this->expectException(\RuntimeException::class);
        // Should throw an exception after 2nd call.
        StreamBuilder::init($this->dependency_bag);
    }

    /**
     * Test running getDependencyBag() method without init() throws an exception.
     * @return void
     */
    public function testGetDependencyBagWithFieldNotSetThrowsRuntimeException()
    {
        $this->expectException(\RuntimeException::class);
        StreamBuilder::getDependencyBag();
    }

    /**
     * Override StreamBuilder::$dependency_bag property.
     * SHOULD ONLY BE USED IN TESTS.
     *
     * @param DependencyBag|null $dependency_bag The bag to override with.
     * @return void
     */
    public static function overrideStreamBuilderInit(?DependencyBag $dependency_bag)
    {
        $reflection_class = new \ReflectionClass(StreamBuilder::class);
        $reflection_property = $reflection_class->getProperty('dependency_bag');
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($dependency_bag);
    }

    /**
     * Reset StreamBuilder::$dependency_bag property to the original config in.
     * @return void
     */
    public static function resetStreamBuilder()
    {
        self::overrideStreamBuilderInit(DependencyBagTest::retrieveDependencyBag());
    }
}
