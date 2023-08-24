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

use Tumblr\StreamBuilder\StreamElementInjection;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use function strval;

/**
 * Class StreamElementInjectionTest
 */
class StreamElementInjectionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test execute
     * @return void
     */
    public function test_execute()
    {
        /** @var StreamInjector $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();
        /** @var StreamElement $element */
        $element = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $stream_ele_injection = new StreamElementInjection($injector, $element);
        $this->assertSame(
            $element,
            $stream_ele_injection->execute(0, [])
        );
    }

    /**
     * Test to_string
     * @return void
     */
    public function test_to_string()
    {
        /** @var StreamInjector $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $element */
        $element = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $element->expects($this->once())
            ->method('to_string')
            ->willReturn('element_string');
        $stream_ele_injection = new StreamElementInjection($injector, $element);
        $this->assertSame(
            'Injection(element_string)',
            strval($stream_ele_injection)
        );
    }

    /**
     * Test get_element
     * @return StreamElementInjection
     */
    public function test_get_element()
    {
        /** @var StreamInjector $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $element */
        $element = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $stream_ele_injection = new StreamElementInjection($injector, $element);
        $this->assertSame(
            $element,
            $stream_ele_injection->get_element()
        );
        return $stream_ele_injection;
    }
}
