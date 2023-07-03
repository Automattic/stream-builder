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

use Tumblr\StreamBuilder\StreamElements\RecommendationLeafStreamElementTrait;

/**
 * Class RecommendationLeafStreamElementTraitTest
 */
class RecommendationLeafStreamElementTraitTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test get_score
     */
    public function test_get_score()
    {
        /** @var float $score */
        $score = 0.5;
        /** @var RecommendationLeafStreamElementTrait $test */
        $test = $this->getMockForTrait(RecommendationLeafStreamElementTrait::class);

        $test->set_score($score);
        $this->assertSame($score, $test->get_score());
    }

    /**
     * Test get_rec_source
     */
    public function test_get_rec_source()
    {
        /** @var float $rec_source */
        $rec_source = "test string";
        /** @var RecommendationLeafStreamElementTrait $test */
        $test = $this->getMockForTrait(RecommendationLeafStreamElementTrait::class);

        $test->set_rec_source($rec_source);
        $this->assertSame($rec_source, $test->get_rec_source());
    }
}
