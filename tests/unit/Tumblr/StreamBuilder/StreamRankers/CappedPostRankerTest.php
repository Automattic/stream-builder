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

use Test\Mock\Tumblr\StreamBuilder\Interfaces\MockedUser;
use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tests\Mock\Tumblr\StreamBuilder\StreamElements\MockedRecommendationStreamElement;
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\Interfaces\PostStreamElementInterface;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamRankers\CappedPostRanker;

/**
 * CappedRankerTest
 */
class CappedPostRankerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var string The identity
     */
    private string $identity;

    /**
     * @var MockedUser The user
     */
    private MockedUser $mock_user;

    /**
     * @var array Array of valid stream elements.
     */
    private array $stream_elements;

    /**
     * @var array Array of invalid stream elements.
     */
    private array $invalid_stream_elements;

    /**
     * @var CappedPostRanker Enabled ranker instance.
     */
    private CappedPostRanker $enabled_ranker_instance;

    /**
     * @var CappedPostRanker Disabled ranker instance.
     */
    private CappedPostRanker $disabled_ranker_instance;

    /**
     * Set up the testcase
     */
    protected function setUp(): void
    {
        $this->identity = '007';
        $this->mock_user = new MockedUser(1234);
        $blog_ids = [111 => [1, 2, 3, 4, 5], 222 => [6, 7], 333 => [8]];
        $this->stream_elements = $this->setup_capped_stream_elements($blog_ids);
        $this->invalid_stream_elements = $this->setup_capped_invalid_stream_elements($blog_ids);

        $this->enabled_ranker_instance = $this->getMockBuilder(CappedPostRanker::class)
            ->setConstructorArgs([$this->mock_user, $this->identity, true, 2, 'dashboard', false, false])
            ->getMock();
        $this->enabled_ranker_instance->method('can_rank')->willReturn(true);

        $this->disabled_ranker_instance = $this->getMockBuilder(CappedPostRanker::class)
            ->setConstructorArgs([$this->mock_user, $this->identity, true, 2, 'dashboard', false, false])
            ->getMock();
        $this->disabled_ranker_instance->method('can_rank')->willReturn(false);
    }

    /**
     * Set up derived stream elements of PostrefStreamElements
     * @param array $blog_ids Dictionary of blog ids and their associated list of posts
     * @return array Array of derived stream elements
     */
    private function setup_capped_stream_elements(array $blog_ids): array
    {
        $elements = [];
        foreach ($blog_ids as $blog_id => $post_ids) {
            foreach ($post_ids as $post_id) {
                $element = new MockedPostRefElement($post_id, $blog_id);
                $elements[] = new DerivedStreamElement(
                    $element,
                    $this->identity
                );
            }
        }
        return $elements;
    }

    /**
     * Set up invalid stream elements to test the exception handling
     * @param array $blog_ids Dictionary of blog ids and their associated list of posts
     * @return array Array of derived stream elements
     */
    private function setup_capped_invalid_stream_elements(array $blog_ids): array
    {
        $elements = [];
        foreach ($blog_ids as $blog_id => $post_ids) {
            $element = new MockedRecommendationStreamElement(
                $blog_id,
                1.0
            );
            $elements[] = new DerivedStreamElement(
                $element,
                $this->identity
            );
        }
        return $elements;
    }

    /**
     * Test the order of the ranked elements
     */
    public function test_rank(): void
    {
        $reranked_elements = $this->enabled_ranker_instance->rank($this->stream_elements);
        $expected_reranking_order = [1 => 111, 2 => 111, 6 => 222, 3 => 111, 7 => 222, 4 => 111, 8 => 333, 5 => 111];
        $actual_reranking_order = [];
        foreach ($reranked_elements as $reranked_elem) {
            /** @var PostStreamElementInterface $oe */
            $oe = $reranked_elem->get_original_element();
            $actual_reranking_order[$oe->getPostId()] = (int) $oe->getBlogId();
        }
        $this->assertSame($actual_reranking_order, $expected_reranking_order);
    }

    /**
     * Test the order of the ranked elements
     */
    public function test_disabled_rank(): void
    {
        $reranked_elements = $this->disabled_ranker_instance->rank($this->stream_elements);
        $expected_reranking_order = [1 => 111, 2 => 111, 3 => 111, 4 => 111, 5 => 111, 6 => 222, 7 => 222, 8 => 333];
        $actual_reranking_order = [];
        foreach ($reranked_elements as $reranked_elem) {
            /** @var PostStreamElementInterface $oe */
            $oe = $reranked_elem->get_original_element();
            $actual_reranking_order[$oe->getPostId()] = (int) $oe->getBlogId();
        }
        $this->assertSame($actual_reranking_order, $expected_reranking_order);
    }

    /**
     * Test invalid elements
     */
    public function test_invalid_elements(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->invokeMethod($this->enabled_ranker_instance, 'pre_fetch', [$this->invalid_stream_elements]);
    }

    /**
     * Test the order of the ranked elements
     */
    public function test_get_blog_dictionaries(): void
    {
        $dictionaries = $this->invokeMethod(
            $this->enabled_ranker_instance,
            'get_blog_dictionaries',
            [$this->stream_elements]
        );
        $post_to_element = $dictionaries[0];
        $blog_to_posts_ids = $dictionaries[1];
        $stats_per_blog = $dictionaries[2];
        foreach ($post_to_element as $post_id => $post_element) {
            $original_element = $post_element->get_original_element();
            $this->assertSame($post_id, $original_element->get_post_id());
        }
        # Which posts belong to which blogs
        $expected_blog_to_posts_ids = [111 => ['1', '2', '3', '4', '5'], 222 => ['6', '7'], 333 => ['8']];
        $this->assertSame($expected_blog_to_posts_ids, $blog_to_posts_ids);
        $expected_stats_per_blog = [111 => [5, 0], 222 => [2, 0], 333 => [1, 0]];
        $this->assertSame($expected_stats_per_blog, $stats_per_blog);
    }

    /**
     * Test that invalidate_post_id properly removes posts from blog_to_posts_ids
     */
    public function test_invalidate_post_id_removes_posts(): void
    {
        $ranker = new CappedPostRanker($this->mock_user, $this->identity, true, 2, 'dashboard', false);

        // Get initial blog dictionaries using reflection
        $dictionaries = $this->invokeMethod($ranker, 'get_blog_dictionaries', [$this->stream_elements]);
        $blog_to_posts_ids = $dictionaries[1];

        // Verify initial state
        $expected_initial = [111 => ['1', '2', '3', '4', '5'], 222 => ['6', '7'], 333 => ['8']];
        $expected_post_counts = [111 => [5, 0], 222 => [2, 0], 333 => [1, 0]];
        $this->assertSame($expected_initial, $blog_to_posts_ids);
        $this->assertSame($expected_post_counts, $dictionaries[2]);

        // Test invalidating a specific post using reflection
        $violated_post_id = $this->invokeMethod($ranker, 'invalidate_post_id', ['111', '3', &$blog_to_posts_ids]);
        // The method returns null when a specific post ID is provided, but the post should be removed
        $this->assertNull($violated_post_id);

        // Verify post was removed from blog 111
        $this->assertCount(4, $blog_to_posts_ids[111]); // Should have 4 posts instead of 5
        $this->assertContains('1', $blog_to_posts_ids[111]);
        $this->assertContains('2', $blog_to_posts_ids[111]);
        $this->assertContains('4', $blog_to_posts_ids[111]);
        $this->assertContains('5', $blog_to_posts_ids[111]);
    }

    /**
     * Test that invalidate_post_id returns the first available post when no specific post is provided
     */
    public function test_invalidate_post_id_returns_first_available(): void
    {
        $ranker = new CappedPostRanker($this->mock_user, $this->identity, true, 2, 'dashboard', false);

        $dictionaries = $this->invokeMethod($ranker, 'get_blog_dictionaries', [$this->stream_elements]);
        $blog_to_posts_ids = $dictionaries[1];

        // Test getting first available post from blog 111 using reflection
        $first_post = $this->invokeMethod($ranker, 'invalidate_post_id', ['111', null, &$blog_to_posts_ids]);
        $this->assertSame(1, $first_post); // Returns integer, not string

        // Verify first post was removed
        $this->assertNotContains('1', $blog_to_posts_ids[111]);
        $this->assertCount(4, $blog_to_posts_ids[111]);
        $this->assertContains('2', $blog_to_posts_ids[111]);
        $this->assertContains('3', $blog_to_posts_ids[111]);
        $this->assertContains('4', $blog_to_posts_ids[111]);
        $this->assertContains('5', $blog_to_posts_ids[111]);
    }

    /**
     * Test that invalidate_post_id handles non-existent posts gracefully
     */
    public function test_invalidate_post_id_nonexistent_post(): void
    {
        $ranker = new CappedPostRanker($this->mock_user, $this->identity, true, 2, 'dashboard', false, false);

        $dictionaries = $this->invokeMethod($ranker, 'get_blog_dictionaries', [$this->stream_elements]);
        $blog_to_posts_ids = $dictionaries[1];

        // Try to invalidate a non-existent post using reflection
        $result = $this->invokeMethod($ranker, 'invalidate_post_id', ['111', '999', &$blog_to_posts_ids]);
        $this->assertNull($result);

        // Verify no changes were made
        $this->assertCount(5, $blog_to_posts_ids[111]);
        $this->assertContains('1', $blog_to_posts_ids[111]);
        $this->assertContains('2', $blog_to_posts_ids[111]);
        $this->assertContains('3', $blog_to_posts_ids[111]);
        $this->assertContains('4', $blog_to_posts_ids[111]);
        $this->assertContains('5', $blog_to_posts_ids[111]);
    }

    /**
     * Test that invalidate_post_id is called during ranking by tracking method calls
     * This test verifies that the escaped mutant (removal of invalidate_post_id call on line 195) would be caught
     */
    public function test_invalidate_post_id_called_during_ranking(): void
    {
        // Create elements from the same blog to force violations with cap=1
        $blog_id = 1000; // Use integer blog ID as expected by MockedPostRefElement
        $elements = [];
        for ($i = 1; $i <= 3; $i++) {
            $post_ref = new MockedPostRefElement($i, $blog_id);
            $elements[] = new DerivedStreamElement($post_ref, 'test_provider');
        }

        // Create a test ranker that tracks invalidate_post_id calls with cap=1 to force violations
        $test_ranker = new class($this->mock_user, $this->identity, true, 1, 'dashboard', false, false) extends CappedPostRanker {
            public $invalidate_post_id_called = false;
            public $invalidate_post_id_calls = [];
            public $debug_info = [];
            
            protected function rank_inner(array $stream_elements, ?\Tumblr\StreamBuilder\StreamTracers\StreamTracer $tracer = null): array
            {
                $this->debug_info[] = "rank_inner called with " . count($stream_elements) . " elements";
                $this->debug_info[] = "can_rank() = " . ($this->can_rank() ? 'true' : 'false');
                
                // Call the parent method and capture the result
                $result = parent::rank_inner($stream_elements, $tracer);
                
                $this->debug_info[] = "rank_inner result count = " . count($result);
                
                // Check if the result is the same as input (no ranking applied)
                if ($result === $stream_elements) {
                    $this->debug_info[] = "No ranking applied - result is same as input";
                } else {
                    $this->debug_info[] = "Ranking was applied - result differs from input";
                }
                
                return $result;
            }
            
            protected function invalidate_post_id(string $blog_id, ?string $post_id, array &$blog_to_posts_ids): ?int
            {
                $this->invalidate_post_id_called = true;
                $this->invalidate_post_id_calls[] = ['blog_id' => $blog_id, 'post_id' => $post_id];
                $this->debug_info[] = "invalidate_post_id called with blog_id: $blog_id, post_id: " . ($post_id ?? 'null');
                $this->debug_info[] = "blog_to_posts_ids before: " . json_encode($blog_to_posts_ids);
                $result = parent::invalidate_post_id($blog_id, $post_id, $blog_to_posts_ids);
                $this->debug_info[] = "blog_to_posts_ids after: " . json_encode($blog_to_posts_ids);
                $this->debug_info[] = "invalidate_post_id result: " . ($result ?? 'null');
                return $result;
            }
        };

        // Call rank method
        $result = $test_ranker->rank($elements);

        // Debug output
        if (!$test_ranker->invalidate_post_id_called) {
            echo "Debug: can_rank() = " . ($test_ranker->can_rank() ? 'true' : 'false') . "\n";
            echo "Debug: result count = " . count($result) . "\n";
            echo "Debug: invalidate_post_id_calls = " . count($test_ranker->invalidate_post_id_calls) . "\n";
            echo "Debug info:\n";
            foreach ($test_ranker->debug_info as $info) {
                echo "  $info\n";
            }
        }

        // Verify that invalidate_post_id was called
        $this->assertTrue($test_ranker->invalidate_post_id_called, 'invalidate_post_id should have been called during ranking');
        $this->assertGreaterThan(0, count($test_ranker->invalidate_post_id_calls), 'invalidate_post_id should have been called at least once');
        $this->assertCount(count($elements), $result);
    }

    /**
     * Test that invalidate_post_id is called during ranking with violations by tracking method calls
     * This test verifies that the escaped mutant (removal of invalidate_post_id call on line 224) would be caught
     */
    public function test_invalidate_post_id_called_during_ranking_with_violations(): void
    {
        // Create a test ranker that tracks invalidate_post_id calls with cap=1 to force violations
        $test_ranker = new class($this->mock_user, $this->identity, true, 1, 'dashboard', false, false) extends CappedPostRanker {
            public $invalidate_post_id_called = false;
            public $invalidate_post_id_calls = [];
            
            protected function invalidate_post_id(string $blog_id, ?string $post_id, array &$blog_to_posts_ids): ?int
            {
                $this->invalidate_post_id_called = true;
                $this->invalidate_post_id_calls[] = ['blog_id' => $blog_id, 'post_id' => $post_id];
                return parent::invalidate_post_id($blog_id, $post_id, $blog_to_posts_ids);
            }
        };

        // Call rank method
        $result = $test_ranker->rank($this->stream_elements);

        // Verify that invalidate_post_id was called
        $this->assertTrue($test_ranker->invalidate_post_id_called, 'invalidate_post_id should have been called during ranking with violations');
        $this->assertGreaterThan(0, count($test_ranker->invalidate_post_id_calls), 'invalidate_post_id should have been called at least once');
        $this->assertCount(count($this->stream_elements), $result);
    }

    /**
     * Helper method to enable testing private and protected methods
     * @param CappedPostRanker $object The object that the private method belongs to
     * @param string $method_name The name of the method we want to test
     * @param array $parameters List of parameters passed to the method
     * @return mixed Whatever the method would return
     * @throws \ReflectionException Something went wrong with reflection
     */
    public function invokeMethod(CappedPostRanker &$object, string $method_name, array $parameters = [])
    {
        $reflection = new \ReflectionClass(CappedPostRanker::class);
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
