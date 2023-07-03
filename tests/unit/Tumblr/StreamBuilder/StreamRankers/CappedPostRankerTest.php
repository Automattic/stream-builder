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
            ->setConstructorArgs([$this->mock_user, $this->identity, false, true, 2, 'dashboard', false])
            ->getMock();
        $this->enabled_ranker_instance->method('can_rank')->willReturn(true);

        $this->disabled_ranker_instance = $this->getMockBuilder(CappedPostRanker::class)
            ->setConstructorArgs([$this->mock_user, $this->identity, false, true, 2, 'dashboard', false])
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
        $this->invokeMethod($this->enabled_ranker_instance, 'pre_fetch', $this->invalid_stream_elements);
    }

    /**
     * Test the order of the ranked elements
     */
    public function test_get_blog_dictionaries(): void
    {
        $dictionaries = $this->invokeMethod(
            $this->enabled_ranker_instance,
            'get_blog_dictionaries',
            $this->stream_elements
        );
        $post_to_element = $dictionaries[0];
        $blog_to_posts_ids = $dictionaries[1];
        $stats_per_blog = $dictionaries[2];
        foreach ($post_to_element as $post_id => $post_element) {
            $original_element = $post_element->get_original_element();
            $this->assertSame($post_id, $original_element->get_post_id());
        }
        # Which posts belong to which blogs
        $expected_blog_to_posts_ids = [111 => [1, 2, 3, 4, 5], 222 => [6, 7], 333 => [8]];
        $this->assertEquals($blog_to_posts_ids, $expected_blog_to_posts_ids);
        $expected_stats_per_blog = [111 => [5, 0], 222 => [2, 0], 333 => [1, 0]];
        $this->assertEquals($stats_per_blog, $expected_stats_per_blog);
    }

    /**
     * Helper method to enable testing private and protected methods
     * @param object $object The object that the private method belongs to
     * @param string $method_name The name of the method we want to test
     * @param array $parameters List of parameters passed to the method
     * @return mixed Whatever the method would return
     * @throws \ReflectionException Something went wrong with reflection
     */
    public function invokeMethod(object &$object, string $method_name, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        if ($method_name = 'get_blog_dictionaries') {
            $parameters = [$parameters];
        }
        return $method->invokeArgs($object, $parameters);
    }
}
