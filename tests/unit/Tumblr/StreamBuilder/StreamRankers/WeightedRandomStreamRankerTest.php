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
use Tests\mock\tumblr\StreamBuilder\StreamElements\MockedRecommendationStreamElement;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamRankers\WeightedRandomStreamRanker;

/**
 * Class WeightedRandomStreamRanker
 */
class WeightedRandomStreamRankerTest extends \PHPUnit\Framework\TestCase
{
    /** @var WeightedRandomStreamRanker Stream ranker */
    private WeightedRandomStreamRanker $ranker;

    /**
     * Set up the testcase
     */
    protected function setUp(): void
    {
        $this->ranker = new WeightedRandomStreamRanker('any_identity');
    }

    /**
     * Test ranker with empty elements
     */
    public function test_ranker_with_empty_elements(): void
    {
        $stream_elements = [];
        $this->assertEmpty($this->ranker->rank($stream_elements));
    }

    /**
     * Test ranker with wrong stream elements
     */
    public function test_ranker_with_wrong_stream_elements(): void
    {
        $stream_elements = [
            new MockedPostRefElement(
                1234,
                3456
            ),
            new MockedPostRefElement(
                2345,
                4567
            ),
        ];
        // should not be ranking not valid elements
        $ranked = $this->ranker->rank($stream_elements);
        $this->assertSame($stream_elements, $ranked);
    }

    /**
     * Test ranker with a mix of wrong stream elements and valid stream elements
     */
    public function test_ranker_with_mix_wrong_stream_elements(): void
    {
        $blog_stream_element = new MockedRecommendationStreamElement(1234, 1.0);
        $post_stream_elements = [
            new MockedPostRefElement(
                1234,
                3456
            ),
            new MockedPostRefElement(
                2345,
                4567
            ),
        ];
        $stream_elements = $post_stream_elements;
        array_push($stream_elements, $blog_stream_element);
        // should not be ranking not valid elements
        $ranked = $this->ranker->rank($stream_elements);
        $first_element = array_shift($ranked);
        $this->assertSame($blog_stream_element, $first_element->get_original_element());
        $this->assertSame($post_stream_elements, $ranked);
    }

    /**
     * Tests ranker with stream elements
     */
    public function test_ranker_with_stream_elements(): void
    {
        $bid2score = [
            1234 => 1.0,
            2345 => 2.0,
            3456 => 3.0,
            4567 => 0.0,
        ];
        $stream_elements = $this->build_blog_stream_elements($bid2score);
        mt_srand(0);
        $ranked_elements = $this->ranker->rank($stream_elements);
        mt_srand(0);
        $weighted_score = array_map(function ($score) {
            return pow(mt_rand() / mt_getrandmax(), (1 / $score));
        }, $bid2score);
        // sort by value in descending order
        arsort($weighted_score);
        $sorted_bid = array_keys($weighted_score);
        foreach ($ranked_elements as $index => $element) {
            /** @var MockedRecommendationStreamElement $original_element */
            $original_element = $element->get_original_element();
            $this->assertSame($sorted_bid[$index], $original_element->get_blog_id());
        }
    }

    /**
     * Test to_template
     * @return array Array of form [$template]
     */
    public function test_to_template(): array
    {
        $template = $this->ranker->to_template();
        $this->assertSame($template, [
            '_type' => WeightedRandomStreamRanker::class,
        ]);
        return [$template];
    }

    /**
     * Test from_template
     * @depends test_to_template
     * @param array $output Output of test to template: [$template]
     */
    public function test_from_template(array $output)
    {
        [$template] = $output;
        $context = new StreamContext($template, ['_type' => WeightedRandomStreamRanker::class]);
        $stream = WeightedRandomStreamRanker::from_template($context);
        $this->assertSame($stream->to_template(), $template);
    }

    /**
     * Builds RecommendBlogStreamElements given an array of blog_id to score
     * @param array $bid2score Array of blog_id => score
     * @return array Array of RecommendBlogStreamElements
     */
    private function build_blog_stream_elements(array $bid2score): array
    {
        $stream_elements = [];
        foreach ($bid2score as $blog_id => $score) {
            $stream_elements[] = new MockedRecommendationStreamElement($blog_id, $score);
        }
        return $stream_elements;
    }
}
