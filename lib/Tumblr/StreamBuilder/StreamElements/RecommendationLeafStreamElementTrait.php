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

namespace Tumblr\StreamBuilder\StreamElements;

/**
 * Trait for RecommendationLeafStreamElements
 */
trait RecommendationLeafStreamElementTrait
{
    /** @var string Name of algorithmic source which provided this recommendation (e.g. "online") */
    private $rec_source;

    /** @var float Score associated to this recommendation (higher = stronger) */
    private $score;

    /**
     * @return float Score associated to this chat recommendation
     */
    public function get_score(): float
    {
        return $this->score;
    }

    /**
     * @param float $updated_score The new score
     * @return void
     */
    public function set_score(float $updated_score): void
    {
        $this->score = $updated_score;
    }

    /**
     * @return string Algorithmic source that provided this chat recommendation
     */
    public function get_rec_source(): string
    {
        return $this->rec_source;
    }

    /**
     * @param string $updated_rec_source The new rec source
     * @return void
     */
    public function set_rec_source(string $updated_rec_source): void
    {
        $this->rec_source = $updated_rec_source;
    }
}
