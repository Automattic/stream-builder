<?php
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

namespace Tumblr\StreamBuilder\StreamRankers;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Re-orders LeafStreamElements by adding normally distributed random noise to the initial rank,
 * and re-order based on the new dithering score.
 */
class DitheringRanker extends StreamRanker
{
    /** @var float Default value of the standard deviation */
    public const STANDARD_DEVIATION_DEFAULT_VALUE = 1e-10;

    public const DEBUG_INFO_KEY = 'dithering_ranker';
    public const DEBUG_INFO_INPUT_RANK_KEY = 'original_rank';
    public const DEBUG_INFO_OUTPUT_RANK_KEY = 'output_rank';
    public const DEBUG_INFO_RANKER_SCORE_KEY = 'score';
    public const DEBUG_INFO_STD_DEV_KEY = 'std_dev';
    public const DEBUG_INFO_AVG_STD_DEV_KEY = 'avg_std_dev';

    /**
     * @var double $epsilon - The magnitude of noise added to the original ranking
     */
    private float $epsilon;

    /**
     * @param string $identity See Identifiable
     * @param float $epsilon The magnitude of noise added to the original ranking
     * @throws \InvalidArgumentException In case it passes an ineligible epsilon value
     */
    public function __construct(
        string $identity,
        float $epsilon
    ) {
        parent::__construct($identity);

        if ($epsilon <= 0.0) {
            throw new \InvalidArgumentException(
                "$epsilon needs to be larger than zero"
            );
        }
        $this->epsilon = $epsilon;
    }

    /**
     * @inheritDoc
     */
    protected function rank_inner(array $stream_elements, StreamTracer $tracer = null): array
    {
        if (empty($stream_elements)) {
            return [];
        }

        [$not_valid_elements, $valid_elements] = $this->select_valid_elements($stream_elements);

        $avg_std_dev = 0;
        $std_dev = ($this->epsilon > 1.0) ?
            sqrt(log($this->epsilon)) :
            DitheringRanker::STANDARD_DEVIATION_DEFAULT_VALUE;

        $element_id_to_score = [];
        $element_id_to_element = [];

        foreach ($valid_elements as $key => $element) {
            /** @var LeafStreamElement $original_el */
            $original_el = $element->get_original_element();

            $original_el->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_INPUT_RANK_KEY, $key);

            $element_id = $original_el->get_element_id();
            $element_id_to_score[$element_id] = (log($key + 1) + $this->draw_from_normal_distribution(
                $avg_std_dev,
                $std_dev
            ));


            $element_id_to_element[$element_id] = $element;
        }

        asort($element_id_to_score);

        $scored_elements = [];
        foreach (array_keys($element_id_to_score) as $current_index => $current_element_id) {
            /** @var LeafStreamElement $current_element */
            $current_element = $element_id_to_element[$current_element_id];

            $current_original_element = $current_element->get_original_element();
            $current_original_element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_OUTPUT_RANK_KEY, $current_index);
            $current_original_element->add_debug_info(
                self::DEBUG_INFO_KEY,
                self::DEBUG_INFO_RANKER_SCORE_KEY,
                $element_id_to_score[$current_element_id]
            );
            $current_original_element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_STD_DEV_KEY, $std_dev);
            $current_original_element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_AVG_STD_DEV_KEY, $avg_std_dev);

            $scored_elements[] = $current_element;
        }

        foreach ($not_valid_elements as $key => $element) {
            $element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_INPUT_RANK_KEY, $key);
            $element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_OUTPUT_RANK_KEY, -1);
            $element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_RANKER_SCORE_KEY, -1);
            $element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_STD_DEV_KEY, -1);
            $element->add_debug_info(self::DEBUG_INFO_KEY, self::DEBUG_INFO_AVG_STD_DEV_KEY, $avg_std_dev);
        }

        return array_merge($scored_elements, $not_valid_elements);
    }

    /**
     * @param array $stream_elements Input stream elements
     * @return array[] 2d Array. [0] => All non LeafStreamElement, [1] => all LeafStreamElement
     */
    private function select_valid_elements(array $stream_elements): array
    {
        $valid_elements = [];
        $not_valid_elements = [];

        foreach ($stream_elements as $stream_element) {
            /** @var StreamElement $stream_element */
            if ($stream_element->get_original_element() instanceof LeafStreamElement) {
                array_push($valid_elements, $stream_element);
            } else {
                array_push($not_valid_elements, $stream_element);
            }
        }

        return [
            0 => $not_valid_elements,
            1 => $valid_elements,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function pre_fetch(array $elements)
    {
        // No need to pre_fetch
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['epsilon'] = $this->epsilon;
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_current_identity(),
            $context->get_required_property('epsilon'),
        );
    }

    /**
     * Generates a single random deviate from a normal distribution
     * @ref https://en.wikipedia.org/wiki/Box%E2%80%93Muller_transform
     * @param float $avg_std_dev Mean of the normal distribution
     * @param float $std_dev Standard Deviation
     * @return float Random deviate
     */
    private function draw_from_normal_distribution(float $avg_std_dev, float $std_dev): float
    {
        $x = mt_rand() / mt_getrandmax();
        $y = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($x)) * cos(2 * pi() * $y) * $std_dev + $avg_std_dev;
    }
}
