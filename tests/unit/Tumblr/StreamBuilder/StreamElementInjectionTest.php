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

    /**
     * Test that component is set on element when element has no component
     * This test verifies that the escaped mutant (removal of setComponent call) would be caught
     */
    public function test_component_is_set_when_element_has_no_component()
    {
        /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();
        
        $injector->method('getComponent')
            ->willReturn('injector_component');

        // Create a test element that tracks component setting
        $element = new class('test_provider') extends StreamElement {
            public $component_set_called = false;
            public $component_value = null;
            
            public function setComponent(?string $component): void {
                $this->component_set_called = true;
                $this->component_value = $component;
                parent::setComponent($component);
            }
            
            public function getComponent(): ?string {
                return $this->component_value;
            }
            
            public function get_element_id(): string {
                return 'test_element';
            }
            
            public function get_original_element(): StreamElement {
                return $this;
            }
            
            public function get_parent_element(): StreamElement {
                return $this;
            }
            
            public function get_cache_key(): string {
                return 'test_cache_key';
            }
            
            public function add_debug_info(string $header, string $field, $value): void {
                // No-op
            }
            
            public function get_debug_info(): array {
                return [];
            }
            
            public function to_string(): string {
                return 'test_element';
            }
            
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context) {
                return new self('test_provider');
            }
        };

        new StreamElementInjection($injector, $element);
        
        // Verify that setComponent was called
        $this->assertTrue($element->component_set_called);
        $this->assertEquals('injector_component', $element->component_value);
    }

    /**
     * Test that component is not set when element already has a component
     */
    public function test_component_is_not_set_when_element_has_existing_component()
    {
        /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();
        
        $injector->method('getComponent')
            ->willReturn('injector_component');

        // Create a test element that already has a component
        $element = new class('test_provider') extends StreamElement {
            public $component_set_called = false;
            
            public function setComponent(?string $component): void {
                $this->component_set_called = true;
                parent::setComponent($component);
            }
            
            public function getComponent(): ?string {
                return 'existing_component';
            }
            
            public function get_element_id(): string {
                return 'test_element';
            }
            
            public function get_original_element(): StreamElement {
                return $this;
            }
            
            public function get_parent_element(): StreamElement {
                return $this;
            }
            
            public function get_cache_key(): string {
                return 'test_cache_key';
            }
            
            public function add_debug_info(string $header, string $field, $value): void {
                // No-op
            }
            
            public function get_debug_info(): array {
                return [];
            }
            
            public function to_string(): string {
                return 'test_element';
            }
            
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context) {
                return new self('test_provider');
            }
        };

        new StreamElementInjection($injector, $element);
        
        // Verify that setComponent was not called
        $this->assertFalse($element->component_set_called);
    }

    /**
     * Test that component is set when element has empty string component
     */
    public function test_component_is_set_when_element_has_empty_component()
    {
        /** @var StreamInjector|\PHPUnit\Framework\MockObject\MockObject $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->setConstructorArgs(['awesome_injector'])
            ->getMock();
        
        $injector->method('getComponent')
            ->willReturn('injector_component');

        // Create a test element with empty component
        $element = new class('test_provider') extends StreamElement {
            public $component_set_called = false;
            public $component_value = null;
            
            public function setComponent(?string $component): void {
                $this->component_set_called = true;
                $this->component_value = $component;
                parent::setComponent($component);
            }
            
            public function getComponent(): ?string {
                return '';
            }
            
            public function get_element_id(): string {
                return 'test_element';
            }
            
            public function get_original_element(): StreamElement {
                return $this;
            }
            
            public function get_parent_element(): StreamElement {
                return $this;
            }
            
            public function get_cache_key(): string {
                return 'test_cache_key';
            }
            
            public function add_debug_info(string $header, string $field, $value): void {
                // No-op
            }
            
            public function get_debug_info(): array {
                return [];
            }
            
            public function to_string(): string {
                return 'test_element';
            }
            
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context) {
                return new self('test_provider');
            }
        };

        new StreamElementInjection($injector, $element);
        
        // Verify that setComponent was called
        $this->assertTrue($element->component_set_called);
        $this->assertEquals('injector_component', $element->component_value);
    }
}