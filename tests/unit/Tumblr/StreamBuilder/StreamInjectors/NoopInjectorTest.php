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

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamInjectors\NoopInjector;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class NoopInjectorTest
 */
class NoopInjectorTest extends \PHPUnit\Framework\TestCase
{
    /** @var Stream */
    protected $stream;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    /**
     * Test plan_injection
     */
    public function test_plan_injection()
    {
        $noop_ij = new NoopInjector('noop');
        $this->assertEquals(
            new InjectionPlan([], null),
            $noop_ij->plan_injection(10, $this->stream)
        );
    }

    /**
     * Test get_identity
     */
    public function test_get_identity()
    {
        $noop_ij = new NoopInjector('noop');
        $this->assertSame('noop', $noop_ij->get_identity());
    }

    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $noop_ij = new NoopInjector('noop');
        $this->assertSame(
            [
                '_type' => NoopInjector::class,
            ],
            $noop_ij->to_template()
        );
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => NoopInjector::class,
        ];
        $context = new StreamContext($template, []);
        $this->assertSame(NoopInjector::from_template($context)->to_template(), $template);
    }
}
