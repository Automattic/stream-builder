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
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamElements\RecommendationLeafStreamElementTrait;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Ranks StreamElements using a weighted random sampling algorithm.
 */
class WeightedRandomStreamRanker extends StreamRanker
{
    /**
     * @inheritDoc
     * @psalm-suppress UndefinedDocblockClass
     */
    #[\Override]
    protected function rank_inner(array $stream_elements, ?StreamTracer $tracer = null): array
    {
        // if there are no stream elements to rank, return early
        if (empty($stream_elements)) {
            return [];
        }

        // select valid stream elements
        [$not_valid_elements, $valid_elements] = $this->select_valid_elements($stream_elements);

        // weighted sampling array, $H: ['sampling score' => $stream_element]
        $H = [];
        /** @var StreamElement $element */
        foreach ($valid_elements as $element) {
            // calculate sampling score
            $r = $this->get_element_random_score($element);

            // store the element in $H, using $r as key.
            $key = strval($r);
            if (array_key_exists($key, $H)) {
                // We don't want to replace an element that was previously added to $H.
                // so we append the element id, if the key already exists.
                $key = sprintf('%s_%s', $key, $element->get_element_id());
            }
            $H[$key] = $element;
        }

        // sort by key in descending order
        krsort($H);
        $ranked_elements = array_values($H);
        return array_merge($ranked_elements, $not_valid_elements);
    }

    /**
     * @param StreamElement $element Stream element to rank randomly
     * @return float|int|object
     */
    protected function get_element_random_score(StreamElement $element)
    {
        /** @var RecommendationLeafStreamElementTrait $original_element */
        $original_element = $element->get_original_element();
        $max_rand = mt_getrandmax();
        $score = $original_element->get_score();
        if ($score == 0.0) {
            $score = 0.001;
        }
        // calculate sampling score
        return pow(mt_rand() / $max_rand, (1 / $score));
    }

    /**
     * Selects stream elements that have a score
     * @param StreamElement[] $stream_elements Stream elements
     * @return array Array of [not_valid_elements, valid_elements]
     */
    private function select_valid_elements(array $stream_elements): array
    {
        $valid_elements = [];
        $not_valid_elements = [];
        foreach ($stream_elements as $element) {
            $original_element = $element->get_original_element();
            // if class $original_element uses a trait RecommendationLeafStreamElementTrait
            if (in_array(RecommendationLeafStreamElementTrait::class, class_uses($original_element), true)) {
                array_push($valid_elements, $element);
            } else {
                array_push($not_valid_elements, $element);
            }
        }
        return [$not_valid_elements, $valid_elements];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        return new self(
            $context->get_current_identity()
        );
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function pre_fetch(array $elements)
    {
        // no need to pre_fetch
    }
}
