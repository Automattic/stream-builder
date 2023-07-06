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

namespace Test\Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\ProportionalMixture;
use function range;

/**
 * Class ProportionalMixtureTest
 */
class ProportionalMixtureTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return array
     */
    public function provider_invalid_constructor()
    {
        return [
            [['foo'], [-1]],
            [['foo'], [0]],
            [['foo'], [-1, 0]],
        ];
    }

    /**
     * @dataProvider provider_invalid_constructor
     * @param array $ids Segment ids.
     * @param array $weights Raw weights.
     */
    public function test_constructor_failure_zero_weight(array $ids, array $weights)
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProportionalMixture($ids, $weights);
    }

    /**
     * To test draw.
     */
    public function test_draw()
    {
        $proportional_mixture = new ProportionalMixture(['foo', 'bar', 'oops', 'aw'], [1, 10, 20, 5]); // Very little risk here
        // If any build failed because of this, it's very lucky day.
        $counts = ['foo' => 0, 'bar' => 0, 'oops' => 0, 'aw' => 0];
        foreach (range(0, 300) as $i) {
            $res = $proportional_mixture->draw();
            $counts[$res]++;
        }
        $this->assertTrue($counts['oops'] > $counts['bar']);
        $this->assertTrue($counts['bar'] > $counts['foo']);
        $this->assertTrue($counts['aw'] > $counts['foo']);
    }
}
