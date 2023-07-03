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

namespace Test\Tumblr\StreamBuilder\StreamCursors;

use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjection;
use Tumblr\StreamBuilder\StreamInjectors\NoopInjector;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\InjectedStream;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class InjectedStreamTest
 *
 * @covers \Tumblr\StreamBuilder\Streams\InjectedStream
 */
class InjectedStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     */
    public function test_to_template()
    {
        $stream = new NullStream('amazing_null_stream');
        $injector = new NoopInjector('amazing_noop_injector');

        $injected_stream = new InjectedStream($stream, $injector, 'amazing_stream');
        $this->assertSame([
            '_type' => InjectedStream::class,
            'injector' => [
                '_type' => NoopInjector::class,
            ],
            'stream' => [
                '_type' => NullStream::class,
            ],
        ], $injected_stream->to_template());
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $template = [
            '_type' => InjectedStream::class,
            'injector' => [
                '_type' => NoopInjector::class,
            ],
            'stream' => [
                '_type' => NullStream::class,
            ],
        ];

        $context = new StreamContext($template, []);
        $stream = InjectedStream::from_template($context);

        $this->assertSame($template, $stream->to_template());
    }

    /**
     * Data Provider.
     * @return array
     */
    public function enumerate_data_provider()
    {
        $el = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $injection = $this->getMockBuilder(StreamInjection::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $injection->expects($this->any())
            ->method('execute')
            ->willReturn($el);

        $plan = new InjectionPlan([1 => $injection, 3 => $injection]);
        $res_0 = new StreamResult(true, []);
        $res_2 = new StreamResult(true, [$el, $el]);
        $res_4 = new StreamResult(true, [$el, $el, $el, $el]);
        $res_5 = new StreamResult(false, [$el, $el, $el, $el, $el]);
        $res_7 = new StreamResult(false, [$el, $el, $el, $el, $el, $el, $el]);

        return [
            [$res_0, $res_0, $plan, $res_2, $res_2],
            [$res_5, $res_2, $plan, $res_7, $res_4],
        ];
    }

    /**
     * Test enumerate
     * @dataProvider enumerate_data_provider
     * @param StreamResult $first_page      First page res.
     * @param StreamResult $second_page     Second Page res.
     * @param InjectionPlan $plan           Injection plan.
     * @param StreamResult $first_page_res  Result for first page.
     * @param StreamResult $second_page_res Result for second page.
     */
    public function test_enumerate(
        StreamResult $first_page,
        StreamResult $second_page,
        InjectionPlan $plan,
        StreamResult $first_page_res,
        StreamResult $second_page_res
    ) {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $stream
            ->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturnOnConsecutiveCalls(
                $first_page,
                $second_page
            );

        /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $injector->expects($this->exactly(2))
            ->method('_plan_injection')
            ->willReturn($plan);

        $injected_stream = new InjectedStream($stream, $injector, 'amazing_injected_stream');
        $this->assert_results_equal($first_page_res, $injected_stream->enumerate(5));
        $this->assert_results_equal($second_page_res, $injected_stream->enumerate(5));
    }

    /**
     * Assert customized stream result parity
     * @param StreamResult $res1 Result 1.
     * @param StreamResult $res2 Result 2.
     * @return void
     */
    private function assert_results_equal(StreamResult $res1, StreamResult $res2)
    {
        $this->assertSame($res1->is_exhaustive(), $res2->is_exhaustive());
        $this->assertSame($res1->get_size(), $res2->get_size());
    }

    /**
     * Test when injection failed, main stream should not be affected.
     */
    public function testIndividualStreamFailure(): void
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMockForAbstractClass();

        /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $injector->expects($this->any())
            ->method('_plan_injection')
            ->willThrowException(new \InvalidArgumentException());
        $injected_stream = new InjectedStream($stream, $injector, 'amazing_injected_stream');
        $result = $injected_stream->enumerate(10);
        $this->assertSame(0, $result->get_size());
    }
}
