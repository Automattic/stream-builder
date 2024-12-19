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
        $max_rand = mt_getrandmax();
        /** @var StreamElement $element */
        foreach ($valid_elements as $element) {
            /** @var RecommendationLeafStreamElementTrait $original_element */
            $original_element = $element->get_original_element();
            // calculate sampling score
            $r = pow(mt_rand() / $max_rand, (1 / $original_element->get_score()));
            $H[strval($r)] = $element;
        }
        // sort by key in descending order
        krsort($H);
        $ranked_elements = array_values($H);
        return array_merge($ranked_elements, $not_valid_elements);
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
    public static function from_template(StreamContext $context): self
    {
        return new self(
            $context->get_current_identity()
        );
    }

    /**
     * @inheritDoc
     */
    protected function pre_fetch(array $elements)
    {
        // no need to pre_fetch
    }
}
