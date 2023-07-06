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

use Test\Mock\Tumblr\StreamBuilder\Interfaces\TestContextProvider;
use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tests\Unit\Tumblr\StreamBuilder\StreamBuilderTest;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\InjectionAllocators\GlobalFixedInjectionAllocator;
use Tumblr\StreamBuilder\Interfaces\Credentials;
use Tumblr\StreamBuilder\Interfaces\Log;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\GeneralStreamInjector;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\TransientCacheProvider;
use function array_slice;
use function reset;

/**
 * Test for GeneralStreamInjector
 */
class GeneralStreamInjectorTest extends \PHPUnit\Framework\TestCase
{
    /** @var StreamInjector Injector */
    private $injector;

    /**
     * Setup
     */
    protected function setUp(): void
    {
        parent::setUp();
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['woo'])
            ->getMock();
        $stream->method('_enumerate')->willReturnCallback(function ($count, $cursor) {
            if ($cursor instanceof StreamCursor) {
                return new StreamResult(false, [$this->getMockPostElement(444)]);
            }
            $elements = [
                $this->getMockPostElement(111),
                $this->getMockPostElement(222),
                $this->getMockPostElement(333),
            ];
            return new StreamResult(false, array_slice($elements, 0, $count));
        });
        $stream->method('to_template')->willReturn([
            '_type' => 'awesome_stream',
        ]);

        $this->injector = new GeneralStreamInjector(
            $stream,
            new GlobalFixedInjectionAllocator([1, 3]),
            'awesome_injector'
        );
        $this->initStreamBuilder();
    }

    /**
     * @param int $post_id Post id
     * @return StreamElement
     */
    private function getMockPostElement(int $post_id): StreamElement
    {
        return new MockedPostRefElement($post_id, 123);
    }

    /**
     * Test to_template.
     */
    public function testToTemplate()
    {
        $template = [
            '_type' => GeneralStreamInjector::class,
            'inner' => [
                '_type' => 'awesome_stream',
            ],
            'allocator' => [
                '_type' => GlobalFixedInjectionAllocator::class,
                'positions' => [1, 3],
            ],
        ];

        $this->assertSame($template, $this->injector->to_template());
    }

    /**
     * Test inject.
     */
    public function testInject(): void
    {
        $main_stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['woo'])->getMock();
        $injection_res = $this->injector->plan_injection(10, $main_stream);
        $this->assertSame(2, $injection_res->get_injection_count());
    }

    /**
     * Test pagination with cursor inside state
     */
    public function testPagination(): void
    {
        $main_stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['woo'])->getMock();
        $array = [];

        $injection_res = $this->injector->plan_injection(2, $main_stream);
        $this->assertSame(1, $injection_res->get_injection_count());
        $injections = $injection_res->get_injections();
        $ele = reset($injections)->execute(0, $array);
        $this->assertSame(111, $ele->get_post_id());

        $injection_res_page_2 = $this->injector->plan_injection(2, $main_stream, $injection_res->get_injector_state());
        $this->assertSame(1, $injection_res_page_2->get_injection_count());
        $injections = $injection_res_page_2->get_injections();
        $ele = reset($injections)->execute(0, $array);
        $this->assertSame(444, $ele->get_post_id());
    }

    /**
     * @return void
     */
    protected function initStreamBuilder(): void
    {
        $log = $this->getMockBuilder(Log::class)->getMock();
        $creds = $this->getMockBuilder(Credentials::class)->getMock();
        $creds->method('get')
            ->willReturn('secret');
        $bag = new DependencyBag(
            $log,
            new TransientCacheProvider(),
            $creds,
            new TestContextProvider()
        );
        StreamBuilderTest::overrideStreamBuilderInit($bag);
    }
}
