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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamInjectors\NoopInjector;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Class StreamInjectorTest
 */
class StreamInjectorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor failure case.
     * @return void
     */
    public function test_constructor_failure()
    {
        $this->expectException(\TypeError::class);
        new NoopInjector(null);
    }

    /**
     * Data provider for plan injection.
     * @return array
     */
    public function plan_injection_provider()
    {
        /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject $stream_ij */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();

        $injector->expects($this->any())
            ->method('_plan_injection')
            ->willReturnCallback(function ($param) {
                switch ($param) {
                    case 10:
                        return new InjectionPlan([], null);
                    case 12:
                        throw new \Exception();
                    default:
                        return null;
                }
            });

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $invalid_injector = new class('injector_foo') extends StreamInjector {
            /**
             * @inheritDoc
             */
            protected function _plan_injection(
                int $page_size,
                Stream $requesting_stream,
                array $state = null,
                StreamTracer $tracer = null
            ): InjectionPlan {
                return InjectionPlan::create_empty_plan();
            }

            /**
             * @inheritDoc
             */
            public function to_template(): array
            {
                return [];
            }

            /**
             * @inheritDoc
             */
            public static function from_template(StreamContext $context)
            {
                return null;
            }

            /**
             * @inheritDoc
             */
            protected function can_inject(): bool
            {
                // this injector is not eligible to inject.
                return false;
            }
        };

        return [
            [
                [
                    'injector' => $injector,
                    'stream' => $stream,
                    'expected' => [],
                    'count' => 10,
                ],
            ],
            [
                [
                    'injector' => $injector,
                    'stream' => $stream,
                    'expected' => 'whatever',
                    'exception' => \Exception::class,
                    'count' => 12,
                ],
            ],
            [
                [
                    'injector' => $invalid_injector,
                    'stream' => $stream,
                    'expected' => [],
                    'count' => 8,
                ],
            ],
        ];
    }

    /**
     * Test plan injection
     * @throws \Exception When designed exception is thrown.
     * @param array $inputs The test inputs.
     * @dataProvider plan_injection_provider
     * @return void
     */
    public function test_plan_injection(array $inputs)
    {
        if ($exception = $inputs['exception'] ?? false) {
            $this->expectException($exception);
        }
        $this->assertSame(
            $inputs['expected'],
            $inputs['injector']->plan_injection($inputs['count'], $inputs['stream'])->get_injections()
        );
    }

    /**
     * Test get identity
     * @return void
     */
    public function test_get_identity()
    {
        $injector = new NoopInjector('wow_amazing_injector');
        $this->assertSame('wow_amazing_injector', $injector->get_identity());
        $this->assertSame('wow_amazing_injector[NoopInjector]', $injector->get_identity(true));
    }
}
