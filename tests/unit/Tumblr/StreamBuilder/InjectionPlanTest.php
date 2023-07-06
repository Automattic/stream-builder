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

use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamElementInjection;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use function count;
use function array_keys;

/**
 * Class InjectionPlanTest
 */
class InjectionPlanTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor
     * @return void
     */
    public function test_constructor_failure()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new InjectionPlan([new \stdClass()], null);
    }

    /**
     * Test get_injection_count
     * @return InjectionPlan
     */
    public function test_get_injection_count()
    {
        $injection = $this->getMockBuilder(StreamElementInjection::class)->disableOriginalConstructor()->getMock();
        $in_plan = new InjectionPlan([2 => $injection, 5 => $injection], null);
        $this->assertSame(2, $in_plan->get_injection_count());
        return $in_plan;
    }

    /**
     * Test get_injections
     * @depends test_get_injection_count
     * @param InjectionPlan $in_plan The injection plan.
     * @return void
     */
    public function test_get_injections(InjectionPlan $in_plan)
    {
        $this->assertSame(2, count($in_plan->get_injections()));
        $this->assertSame([2, 5], array_keys($in_plan->get_injections()));
    }

    /**
     * Test get_injector_state
     * @return void
     */
    public function test_get_injector_state()
    {
        $injection = $this->getMockBuilder(StreamElementInjection::class)->disableOriginalConstructor()->getMock();
        $in_plan = new InjectionPlan([2 => $injection], [1]);
        $this->assertSame([1], $in_plan->get_injector_state());
    }

    /**
     * Test apply
     * @return void
     */
    public function test_apply()
    {
        $element = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $injection = $this->getMockBuilder(StreamElementInjection::class)->disableOriginalConstructor()->getMock();

        $injection
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                null,
                $element,
            );

        $result = new StreamResult(false, [$element, $element]);
        $in_plan = new InjectionPlan([5 => $injection, 1 => $injection], null);

        $full_result = $in_plan->apply($result);
        $this->assertSame(3, $full_result->get_size());
        $this->assertSame([0, 1, 2], array_keys($full_result->get_elements()));
    }

    /**
     * Test the static create_empty_plan method.
     * @return void
     */
    public function test_create_empty_plan()
    {
        $empty_plan = InjectionPlan::create_empty_plan();
        $this->assertSame([], $empty_plan->get_injections());
        $this->assertSame(0, $empty_plan->get_injection_count());
        $this->assertNull($empty_plan->get_injector_state());
    }
}
