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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamRankers\DitheringRanker;
use Tumblr\StreamBuilder\StreamSerializer;
use function sqrt;
use function log;
use function array_search;

class DitheringRankerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for test_constructor_failure
     * @return array[]
     */
    public function invalid_constructor_provider(): array
    {
        $valid_identity = 'any_identity';

        return [
            'epsilon is equal to zero' => [$valid_identity, 0.0, \InvalidArgumentException::class],
            'epsilon a negative number' => [$valid_identity, -1.0, \InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider invalid_constructor_provider
     * @param string $identity See Identifiable
     * @param float $epsilon The magnitude of noise added to the original ranking
     * @param string $error Error Type
     * @return void
     */
    public function test_constructor_failure(string $identity, float $epsilon, string $error)
    {
        $this->expectException($error);
        new DitheringRanker($identity, $epsilon);
    }

    /**
     * Constructor Succeeds
     */
    public function test_constructor_success()
    {
        $identity = 'any_identity';
        $epsilon = 1.5;
        $instance = new DitheringRanker($identity, $epsilon);

        $this->assertSame(
            $identity,
            $instance->get_identity()
        );
    }

    /**
     * Test to_template function which serializes an object to template and return an array with (original stream, template)
     * @return array Includes Original Stream and Template
     */
    public function test_to_template(): array
    {
        $identity = 'any_identity';
        $epsilon = 1.5;
        $ranker = new DitheringRanker($identity, $epsilon);
        $template = $ranker->to_template();

        $this->assertSame($template, [
            '_type' => DitheringRanker::class,
            'epsilon' => $epsilon,
        ]);

        return [$template];
    }

    /**
     * @param array $output Output of test to template: [$template]
     * @depends test_to_template
     * @return void
     */
    public function test_from_template(array $output)
    {
        [$template] = $output;
        $candidate = StreamSerializer::from_template(new StreamContext($template, []));
        $this->assertSame($candidate->to_template(), $template);
    }

    /**
     * The result of the ranker should be empty when given empty elements to rank
     */
    public function test_rank_when_given_empty_elements()
    {
        $identity = 'any_identity';
        $epsilon = 1.5;
        $empty_elements = [];
        $ranker = new DitheringRanker($identity, $epsilon);
        $scored_result = $ranker->rank($empty_elements);

        $this->assertEmpty($scored_result);
    }

    /**
     * The result of the ranker should return a different element order
     */
    public function test_dithering_with_valid_elements()
    {
        $identity = 'any_identity';
        $epsilon = 1.5;
        $avg_std_dev = 0;
        $std_dev = ($epsilon > 1.0) ?
            sqrt(log($epsilon)) :
            DitheringRanker::STANDARD_DEVIATION_DEFAULT_VALUE;

        $post_id_1 = 609608667641266176;
        $post_id_2 = 609608667633876992;
        $post_id_3 = 683561403212316672;
        $post_id_4 = 683182086989004800;
        $post_id_5 = 684091701475868672;

        $mock_element_1 = $this->buildMockedPostRefElement($post_id_1);
        $mock_element_2 = $this->buildMockedPostRefElement($post_id_2);
        $mock_element_3 = $this->buildMockedPostRefElement($post_id_3);
        $mock_element_4 = $this->buildMockedPostRefElement($post_id_4);
        $mock_element_5 = $this->buildMockedPostRefElement($post_id_5);
        $elements = [$mock_element_1, $mock_element_2, $mock_element_3, $mock_element_4, $mock_element_5];


        $ranker = new DitheringRanker($identity, $epsilon);
        $scored_result = $ranker->rank($elements);

        $this->assertSame(
            array_search($scored_result[0], $elements),
            $scored_result[0]
                ->get_original_element()
                ->get_debug_info()[DitheringRanker::DEBUG_INFO_KEY][DitheringRanker::DEBUG_INFO_INPUT_RANK_KEY]
        );

        $this->assertSame(
            array_search($scored_result[0], $scored_result),
            $scored_result[0]
                ->get_original_element()
                ->get_debug_info()[DitheringRanker::DEBUG_INFO_KEY][DitheringRanker::DEBUG_INFO_OUTPUT_RANK_KEY]
        );

        $this->assertSame(
            $avg_std_dev,
            $scored_result[0]
                ->get_original_element()
                ->get_debug_info()[DitheringRanker::DEBUG_INFO_KEY][DitheringRanker::DEBUG_INFO_AVG_STD_DEV_KEY]
        );

        $this->assertSame(
            $std_dev,
            $scored_result[0]
                ->get_original_element()
                ->get_debug_info()[DitheringRanker::DEBUG_INFO_KEY][DitheringRanker::DEBUG_INFO_STD_DEV_KEY]
        );

        $this->assertSameSize(
            $elements,
            $scored_result
        );
    }

    /**
     * The result of the ranker should return same element order when elements
     * are not LeafStreamElement
     */
    public function test_dithering_with_invalid_elements()
    {
        $identity = 'any_identity';
        $epsilon = 1.5;
        $ranker = new DitheringRanker($identity, $epsilon);

        $mock_element_1 = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_element_1->method('get_original_element')->willReturn($mock_element_1);

        $mock_element_2 = $this->getMockBuilder(StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_element_2->method('get_original_element')->willReturn($mock_element_2);

        $not_valid_elements = [$mock_element_1, $mock_element_2];
        $scored_result = $ranker->rank($not_valid_elements);

        $this->assertSame(
            $not_valid_elements,
            $scored_result
        );
    }

    /**
     * @param int $post_id Post id.
     * @return MockedPostRefElement Mock of LeafStreamElement
     */
    private function buildMockedPostRefElement(int $post_id): MockedPostRefElement
    {
        return new MockedPostRefElement($post_id, 0);
    }
}
