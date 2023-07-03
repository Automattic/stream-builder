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

namespace Tumblr\StreamBuilder;

/**
 * A utility class for managing a distribution over several buckets, each with a relative real-valued weight.
 */
final class ProportionalMixture
{
    /** @var array */
    private $segment_ids;
    /** @var array */
    private $cumulative_weights;

    /**
     * @param array $segment_ids An array, each element representing a bucket.
     * The elements can be of any type.
     * @param array $raw_weights An array, each element a float representing the
     * weight of the segment at the same index. The two arrays must be the same length.
     * @throws \InvalidArgumentException If input arrays don't match or value invalid.
     */
    public function __construct(array $segment_ids, array $raw_weights)
    {
        if (count($segment_ids) != count($raw_weights)) {
            throw new \InvalidArgumentException("Segments and weights do not have the same cardinality");
        }
        $cumulative_weights = [];
        $weight_sum = array_sum($raw_weights);
        $cumulative_sum = 0.0;
        foreach ($raw_weights as $i) {
            if ($i <= 0.0) {
                throw new \InvalidArgumentException("Encountered negative or zero weight");
            }
            $cumulative_weights[] = ($cumulative_sum += ($i / $weight_sum));
        }
        $this->segment_ids = $segment_ids;
        $this->cumulative_weights = $cumulative_weights;
    }

    /**
     * Draw a bucket randomly, in proportion to the weights assigned.
     * @return mixed The segment id for the chosen bucket.
     */
    public function draw()
    {
        $r = mt_rand() / mt_getrandmax();
        $idx = 0;
        $end = count($this->segment_ids) - 1;
        // 0 -> size - 1
        for ($j = 0; $j < $end; $j++) {
            if ($r > $this->cumulative_weights[$j] && $r < $this->cumulative_weights[$j + 1] ?? 2.0) {
                $idx = $j + 1;
                break;
            }
        }
        return $this->segment_ids[$idx];
    }
}
