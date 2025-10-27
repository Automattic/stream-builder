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

namespace Tests\Unit\Tumblr\StreamBuilder\StreamRankers;

use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Class StreamRankerTest
 */
class StreamRankerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that pre_fetch is called during ranking
     * This test verifies that the escaped mutant (removal of pre_fetch call) would be caught
     */
    public function test_pre_fetch_is_called_during_ranking(): void
    {
        // Create a test ranker that tracks pre_fetch calls
        $test_ranker = new class('test_ranker') extends StreamRanker {
            /** @var bool */
            public $pre_fetch_called = false;
            /** @var array */
            public $pre_fetch_elements = [];

            /**
             * @param array $elements The elements to pre-fetch
             * @return void
             */
            protected function pre_fetch(array $elements): void
            {
                $this->pre_fetch_called = true;
                $this->pre_fetch_elements = $elements;
            }

            /**
             * @param array $stream_elements The stream elements to rank
             * @param StreamTracer|null $tracer Optional tracer for debugging
             * @return array The ranked elements
             */
            protected function rank_inner(array $stream_elements, ?StreamTracer $tracer = null): array
            {
                // Just return the elements in the same order
                return $stream_elements;
            }

            /**
             * @return array The template array
             */
            public function to_template(): array
            {
                return ['_type' => 'TestRanker'];
            }

            /**
             * @param \Tumblr\StreamBuilder\StreamContext $context The stream context
             * @return self The created instance
             */
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context): self
            {
                return new self('test_ranker');
            }
        };

        // Create test elements
        $element1 = new MockedPostRefElement(1, 1);
        $element2 = new MockedPostRefElement(2, 2);
        $elements = [$element1, $element2];

        // Call rank method
        $result = $test_ranker->rank($elements);

        // Verify that pre_fetch was called
        $this->assertTrue($test_ranker->pre_fetch_called);
        $this->assertSame($elements, $test_ranker->pre_fetch_elements);
        $this->assertSame($elements, $result);
    }

    /**
     * Test that pre_fetch is called with tracer during ranking
     */
    public function test_pre_fetch_is_called_with_tracer_during_ranking(): void
    {
        // Create a test ranker that tracks pre_fetch calls
        $test_ranker = new class('test_ranker') extends StreamRanker {
            /** @var bool */
            public $pre_fetch_called = false;
            /** @var array */
            public $pre_fetch_elements = [];

            /**
             * @param array $elements The elements to pre-fetch
             * @return void
             */
            protected function pre_fetch(array $elements): void
            {
                $this->pre_fetch_called = true;
                $this->pre_fetch_elements = $elements;
            }

            /**
             * @param array $stream_elements The stream elements to rank
             * @param StreamTracer|null $tracer Optional tracer for debugging
             * @return array The ranked elements
             */
            protected function rank_inner(array $stream_elements, ?StreamTracer $tracer = null): array
            {
                // Just return the elements in the same order
                return $stream_elements;
            }

            /**
             * @return array The template array
             */
            public function to_template(): array
            {
                return ['_type' => 'TestRanker'];
            }

            /**
             * @param \Tumblr\StreamBuilder\StreamContext $context The stream context
             * @return self The created instance
             */
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context): self
            {
                return new self('test_ranker');
            }
        };

        // Create a mock tracer
        $tracer = $this->getMockBuilder(StreamTracer::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create test elements
        $element1 = new MockedPostRefElement(1, 1);
        $element2 = new MockedPostRefElement(2, 2);
        $elements = [$element1, $element2];

        // Call rank method with tracer
        $result = $test_ranker->rank($elements, $tracer);

        // Verify that pre_fetch was called
        $this->assertTrue($test_ranker->pre_fetch_called);
        $this->assertSame($elements, $test_ranker->pre_fetch_elements);
        $this->assertSame($elements, $result);
    }

    /**
     * Test that pre_fetch is called even when rank_inner throws an exception
     */
    public function test_pre_fetch_is_called_even_when_rank_inner_throws_exception(): void
    {
        // Create a test ranker that tracks pre_fetch calls and throws exception
        $test_ranker = new class('test_ranker') extends StreamRanker {
            /** @var bool */
            public $pre_fetch_called = false;
            /** @var array */
            public $pre_fetch_elements = [];

            /**
             * @param array $elements The elements to pre-fetch
             * @return void
             */
            protected function pre_fetch(array $elements): void
            {
                $this->pre_fetch_called = true;
                $this->pre_fetch_elements = $elements;
            }

            /**
             * @param array $stream_elements The stream elements to rank
             * @param StreamTracer|null $tracer Optional tracer for debugging
             * @return never Always throws exception
             * @throws \Exception Always throws test exception
             */
            protected function rank_inner(array $stream_elements, ?StreamTracer $tracer = null): array
            {
                // Throw an exception to test that pre_fetch is still called
                throw new \Exception('Test exception');
            }

            /**
             * @return array The template array
             */
            public function to_template(): array
            {
                return ['_type' => 'TestRanker'];
            }

            /**
             * @param \Tumblr\StreamBuilder\StreamContext $context The stream context
             * @return self The created instance
             */
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context): self
            {
                return new self('test_ranker');
            }
        };

        // Create test elements
        $element1 = new MockedPostRefElement(1, 1);
        $element2 = new MockedPostRefElement(2, 2);
        $elements = [$element1, $element2];

        // Expect an exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $test_ranker->rank($elements);
        } finally {
            // Verify that pre_fetch was called even though an exception was thrown
            $this->assertTrue($test_ranker->pre_fetch_called);
            $this->assertSame($elements, $test_ranker->pre_fetch_elements);
        }
    }

    /**
     * Test that pre_fetch is called with multiple elements
     */
    public function test_pre_fetch_is_called_with_multiple_elements(): void
    {
        // Create a test ranker that tracks pre_fetch calls
        $test_ranker = new class('test_ranker') extends StreamRanker {
            /** @var bool */
            public $pre_fetch_called = false;
            /** @var array */
            public $pre_fetch_elements = [];

            /**
             * @param array $elements The elements to pre-fetch
             * @return void
             */
            protected function pre_fetch(array $elements): void
            {
                $this->pre_fetch_called = true;
                $this->pre_fetch_elements = $elements;
            }

            /**
             * @param array $stream_elements The stream elements to rank
             * @param StreamTracer|null $tracer Optional tracer for debugging
             * @return array The ranked elements
             */
            protected function rank_inner(array $stream_elements, ?StreamTracer $tracer = null): array
            {
                // Just return the elements in the same order
                return $stream_elements;
            }

            /**
             * @return array The template array
             */
            public function to_template(): array
            {
                return ['_type' => 'TestRanker'];
            }

            /**
             * @param \Tumblr\StreamBuilder\StreamContext $context The stream context
             * @return self The created instance
             */
            public static function from_template(\Tumblr\StreamBuilder\StreamContext $context): self
            {
                return new self('test_ranker');
            }
        };

        // Create multiple test elements
        $elements = [];
        for ($i = 1; $i <= 5; $i++) {
            $elements[] = new MockedPostRefElement($i, $i);
        }

        // Call rank method
        $result = $test_ranker->rank($elements);

        // Verify that pre_fetch was called with all elements
        $this->assertTrue($test_ranker->pre_fetch_called);
        $this->assertSame($elements, $test_ranker->pre_fetch_elements);
        $this->assertSame($elements, $result);
        $this->assertCount(5, $test_ranker->pre_fetch_elements);
    }
}
