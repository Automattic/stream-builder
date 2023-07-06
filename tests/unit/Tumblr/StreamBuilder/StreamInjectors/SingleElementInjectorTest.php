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

use Test\Tumblr\StreamBuilder\StreamElements\TestingRankableChronoStreamElement;
use Tumblr\StreamBuilder\InjectionAllocators\GlobalFixedInjectionAllocator;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\SingleElementInjector;
use Tumblr\StreamBuilder\Streams\Stream;
use function reset;

/**
 * Test for SingleElementInjector
 */
class SingleElementInjectorTest extends \PHPUnit\Framework\TestCase
{
    /** @var SingleElementInjector Injector */
    private $injector;

    /**
     * Setup
     */
    protected function setUp(): void
    {
        $allocator = new GlobalFixedInjectionAllocator([0, 6]);
        $test_injector = new class($allocator, 'test') extends SingleElementInjector {
            /**
             * @inheritDoc
             */
            protected function get_inject_element(): StreamElement
            {
                return new TestingRankableChronoStreamElement('nobody', 0, 1);
            }
            /**
             * @inheritDoc
             */
            public static function from_template(StreamContext $context)
            {
                new self(
                    $context->deserialize_required_property('stream_injection_allocator'),
                    'test'
                );
            }
        };
        $this->injector = $test_injector;
    }

    /**
     * Test to_template
     */
    public function testToTemplate(): void
    {
        $allocator_template = [
            '_type' => GlobalFixedInjectionAllocator::class,
            'positions' => [0, 6],
        ];
        $this->assertSame($allocator_template, $this->injector->to_template()['stream_injection_allocator']);
    }

    /**
     * Test pagination get injecting same element
     */
    public function testPagination(): void
    {
        $main_stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['woo'])->getMock();
        $array = [];

        $injection_res = $this->injector->plan_injection(5, $main_stream);
        $this->assertSame(1, $injection_res->get_injection_count());
        $injections = $injection_res->get_injections();
        $ele = reset($injections)->execute(0, $array);
        $this->assertSame(0, $ele->get_timestamp_ms());

        $injection_res_page_2 = $this->injector->plan_injection(5, $main_stream, $injection_res->get_injector_state());
        $this->assertSame(1, $injection_res_page_2->get_injection_count());
        $injections = $injection_res_page_2->get_injections();
        $ele = reset($injections)->execute(0, $array);
        $this->assertSame(0, $ele->get_timestamp_ms());

        $injection_res_page_3 = $this->injector->plan_injection(5, $main_stream, $injection_res_page_2->get_injector_state());
        $this->assertSame(0, $injection_res_page_3->get_injection_count());
    }
}
