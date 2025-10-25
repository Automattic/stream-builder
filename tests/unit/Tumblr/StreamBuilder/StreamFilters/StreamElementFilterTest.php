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

namespace Test\Tumblr\StreamBuilder\StreamFilters;

use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilters\StreamElementFilter;

/**
 * Class StreamElementFilterTest
 */
class StreamElementFilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that pre_fetch is called during filtering
     * This test verifies that the escaped mutant (removal of pre_fetch call) would be caught
     */
    public function test_pre_fetch_is_called_during_filtering(): void
    {
        // Create a test filter that tracks pre_fetch calls
        $test_filter = new class('test_filter') extends StreamElementFilter {
            public $pre_fetch_called = false;
            public $pre_fetch_elements = [];
            
            protected function pre_fetch(array $elements): void
            {
                $this->pre_fetch_called = true;
                $this->pre_fetch_elements = $elements;
            }
            
            protected function should_release(StreamElement $e): bool
            {
                // Always retain elements for this test
                return false;
            }
            
            public function get_cache_key(): ?string
            {
                return 'test_filter_cache_key';
            }
            
            public function to_template(): array
            {
                return ['_type' => 'TestFilter'];
            }
            
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context): self
            {
                return new self('test_filter');
            }
        };

        // Create test elements
        $element1 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['test1'])
            ->getMockForAbstractClass();
        $element2 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['test2'])
            ->getMockForAbstractClass();
        
        $elements = [$element1, $element2];

        // Call filter method
        $result = $test_filter->filter($elements);

        // Verify that pre_fetch was called
        $this->assertTrue($test_filter->pre_fetch_called);
        $this->assertSame($elements, $test_filter->pre_fetch_elements);
        $this->assertSame($elements, $result->get_retained());
        $this->assertEmpty($result->get_released());
    }

    /**
     * Test that pre_fetch is called with multiple elements
     */
    public function test_pre_fetch_is_called_with_multiple_elements(): void
    {
        // Create a test filter that tracks pre_fetch calls
        $test_filter = new class('test_filter') extends StreamElementFilter {
            public $pre_fetch_called = false;
            public $pre_fetch_elements = [];
            
            protected function pre_fetch(array $elements): void
            {
                $this->pre_fetch_called = true;
                $this->pre_fetch_elements = $elements;
            }
            
            protected function should_release(StreamElement $e): bool
            {
                // Always retain elements for this test
                return false;
            }
            
            public function get_cache_key(): ?string
            {
                return 'test_filter_cache_key';
            }
            
            public function to_template(): array
            {
                return ['_type' => 'TestFilter'];
            }
            
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context): self
            {
                return new self('test_filter');
            }
        };

        // Create multiple test elements
        $elements = [];
        for ($i = 1; $i <= 5; $i++) {
            $elements[] = $this->getMockBuilder(StreamElement::class)
                ->setConstructorArgs(["test{$i}"])
                ->getMockForAbstractClass();
        }

        // Call filter method
        $result = $test_filter->filter($elements);

        // Verify that pre_fetch was called with all elements
        $this->assertTrue($test_filter->pre_fetch_called);
        $this->assertSame($elements, $test_filter->pre_fetch_elements);
        $this->assertSame($elements, $result->get_retained());
        $this->assertEmpty($result->get_released());
        $this->assertCount(5, $test_filter->pre_fetch_elements);
    }

    /**
     * Test that pre_fetch is called even when some elements are released
     */
    public function test_pre_fetch_is_called_even_when_elements_are_released(): void
    {
        // Create a test filter that tracks pre_fetch calls
        $test_filter = new class('test_filter') extends StreamElementFilter {
            public $pre_fetch_called = false;
            public $pre_fetch_elements = [];
            
            protected function pre_fetch(array $elements): void
            {
                $this->pre_fetch_called = true;
                $this->pre_fetch_elements = $elements;
            }
            
            protected function should_release(StreamElement $e): bool
            {
                // Release elements with '2' or '4' in their identity
                return (strpos($e->get_element_id(), '2') !== false || strpos($e->get_element_id(), '4') !== false);
            }
            
            public function get_cache_key(): ?string
            {
                return 'test_filter_cache_key';
            }
            
            public function to_template(): array
            {
                return ['_type' => 'TestFilter'];
            }
            
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context): self
            {
                return new self('test_filter');
            }
        };

        // Create test elements
        $element1 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['test1'])
            ->getMockForAbstractClass();
        $element2 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['test2'])
            ->getMockForAbstractClass();
        $element3 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['test3'])
            ->getMockForAbstractClass();
        $element4 = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs(['test4'])
            ->getMockForAbstractClass();
        
        // Mock the get_element_id method to return the identity
        $element1->method('get_element_id')->willReturn('test1');
        $element2->method('get_element_id')->willReturn('test2');
        $element3->method('get_element_id')->willReturn('test3');
        $element4->method('get_element_id')->willReturn('test4');
        
        $elements = [$element1, $element2, $element3, $element4];

        // Call filter method
        $result = $test_filter->filter($elements);

        // Verify that pre_fetch was called
        $this->assertTrue($test_filter->pre_fetch_called);
        $this->assertSame($elements, $test_filter->pre_fetch_elements);
        $this->assertCount(2, $result->get_retained());
        $this->assertCount(2, $result->get_released());
    }
}
