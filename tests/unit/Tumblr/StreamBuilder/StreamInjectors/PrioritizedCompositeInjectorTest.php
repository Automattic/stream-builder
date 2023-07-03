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

namespace Tests\Unit\Tumblr\StreamBuilder\StreamInjectors;

use Tests\Unit\Tumblr\StreamBuilder\TestUtils;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamElementInjection;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamInjectors\PrioritizedCompositeInjector;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Test for PrioritizedCompositeInjector
 */
class PrioritizedCompositeInjectorTest extends \PHPUnit\Framework\TestCase
{
    /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject */
    protected $sj1;
    /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject */
    protected $sj2;
    /** @var Stream|\PHPUnit\Framework\MockObject\MockObject */
    protected $stream;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->sj1 = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector_1'])
            ->getMock();
        $this->sj2 = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector_2'])
            ->getMock();
        $this->stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['awesome_stream'])
            ->getMock();
    }

    /**
     * @return void
     */
    public function test_constructor_failure()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new PrioritizedCompositeInjector([new \stdClass()], 'cool_injector');
    }

    /**
     * @return void
     */
    public function test_plan_injection_empty()
    {
        $this->sj1->expects($this->any())
            ->method('_plan_injection')
            ->willReturn(
                new InjectionPlan([], null)
            );
        $this->sj2->expects($this->any())
            ->method('_plan_injection')
            ->willReturn(
                new InjectionPlan([], null)
            );
        $pushdown_composite_ij = new PrioritizedCompositeInjector(
            [$this->sj1, $this->sj2],
            'cool_injector'
        );
        TestUtils::assertSameRecursively(
            new InjectionPlan([], null),
            $pushdown_composite_ij->plan_injection(10, $this->stream)
        );
    }

    /**
     * @return void
     */
    public function test__plan_injection()
    {
        /** @var LeafStreamElement $el1 */
        $el1 = $this->getMockBuilder(LeafStreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var LeafStreamElement $el2 */
        $el2 = $this->getMockBuilder(LeafStreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var LeafStreamElement $el3 */
        $el3 = $this->getMockBuilder(LeafStreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $in1 = new StreamElementInjection($this->sj1, $el1);
        $in2 = new StreamElementInjection($this->sj1, $el2);
        $in3 = new StreamElementInjection($this->sj2, $el3);

        $in_plan1 = new InjectionPlan([2 => $in1, 5 => $in2], null);
        $in_plan2 = new InjectionPlan([4 => $in3], [7, 10]);
        $in_plan3 = new InjectionPlan([5 => $in3], null);

        $this->sj1->expects($this->any())
            ->method('_plan_injection')
            ->willReturn($in_plan1);
        $this->sj2->expects($this->any())
            ->method('_plan_injection')
            ->willReturn($in_plan2);
        $prioritized_composite_ij = new PrioritizedCompositeInjector([$this->sj1, $this->sj2], 'cool_injector');

        TestUtils::assertSameRecursively(
            new InjectionPlan([2 => $in1, 5 => $in2, 4 => $in3], ['awesome_injector_2' => [7, 10]]),
            $prioritized_composite_ij->plan_injection(10, $this->stream)
        );
        TestUtils::assertSameRecursively(
            new InjectionPlan([2 => $in1], ['awesome_injector_2' => [7, 10]]),
            $prioritized_composite_ij->plan_injection(3, $this->stream)
        );
        TestUtils::assertSameRecursively(
            new InjectionPlan([2 => $in1, 4 => $in3], ['awesome_injector_2' => [7, 10]]),
            $prioritized_composite_ij->plan_injection(5, $this->stream)
        );
        TestUtils::assertSameRecursively(
            new InjectionPlan([], null),
            $prioritized_composite_ij->plan_injection(0, $this->stream),
            'Page size too small'
        );

        $sj3 = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector_3'])
            ->getMock();
        $sj3->expects($this->any())
            ->method('_plan_injection')
            ->willReturn($in_plan3);
        $prioritized_composite_ij = new PrioritizedCompositeInjector([$this->sj1, $sj3], 'cool_injector');
        TestUtils::assertSameRecursively(
            new InjectionPlan([2 => $in1, 5 => $in2], null),
            $prioritized_composite_ij->plan_injection(10, $this->stream),
            'Injection position collision'
        );
    }

    /**
     * @return void
     */
    public function test_get_identity()
    {
        $pushdown_composite_ij = new PrioritizedCompositeInjector(
            [$this->sj1, $this->sj2],
            'cool_injector'
        );
        $this->assertSame('cool_injector', $pushdown_composite_ij->get_identity());
    }

    /**
     * @return void
     */
    public function test_to_template()
    {
        $this->sj1->expects($this->once())
            ->method('to_template')
            ->willReturn(['_type' => 'StreamInjector']);
        $this->sj2->expects($this->once())
            ->method('to_template')
            ->willReturn(['_type' => 'StreamInjector']);
        $pushdown_composite_ij = new PrioritizedCompositeInjector(
            [$this->sj1, $this->sj2],
            'cool_injector'
        );
        $this->assertSame([
            '_type' => PrioritizedCompositeInjector::class,
            'stream_injector_array' => [
                ['_type' => 'StreamInjector'],
                ['_type' => 'StreamInjector'],
            ],
        ], $pushdown_composite_ij->to_template());
    }
}
