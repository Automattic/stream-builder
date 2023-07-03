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

namespace Test\Tumblr\StreamBuilder\StreamElements;

use Test\Tumblr\StreamBuilder\StreamCursors\TestingChronoCursor;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;

/**
 * A stream element for {@see TestingRankableChronoStream}
 */
final class TestingRankableChronoStreamElement extends LeafStreamElement implements ChronologicalStreamElement
{
    /** @var int */
    private $timestamp_ms;
    /** @var float */
    private $rank_ordinal;

    /**
     * @param string $provider_identity The id of the provider of this element.
     * @param int $timestamp_ms The timestamp of this element.
     * @param float $rank_ordinal The rank of this element, higher ranks are "better".
     */
    public function __construct(string $provider_identity, int $timestamp_ms, float $rank_ordinal)
    {
        parent::__construct($provider_identity, new TestingChronoCursor($timestamp_ms));
        $this->timestamp_ms = $timestamp_ms;
        $this->rank_ordinal = $rank_ordinal;
    }

    /**
     * @inheritDoc
     */
    public function get_timestamp_ms(): int
    {
        return $this->timestamp_ms;
    }

    /**
     * @return float
     */
    public function get_rank_ordinal()
    {
        return $this->rank_ordinal;
    }

    /**
     * @inheritDoc
     */
    public function get_cache_key()
    {
        return $this->to_string();
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('TestingRankableChronoStreamElement(%d, %f)', $this->timestamp_ms, $this->rank_ordinal);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['timestamp_ms'] = $this->timestamp_ms;
        $base['rank_ordinal'] = $this->rank_ordinal;
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_required_property('provider_id'),
            $context->get_required_property('timestamp_ms'),
            $context->get_required_property('rank_ordinal')
        );
    }

    /**
     * @param int $count How many to create
     * @param float $rank_factor The factor to determine rank.
     * @param int $max_ts The max ts
     * @param int $step The ts step
     * @return self[]
     */
    public static function create_sequence(int $count, float $rank_factor, int $max_ts, int $step)
    {
        $res = [];
        for ($i = 0; $i < $count; $i++) {
            $ts = ($max_ts - ($step * $i));
            $res[] = new self('nobody', $ts, $rank_factor * $ts);
        }
        return $res;
    }
}
