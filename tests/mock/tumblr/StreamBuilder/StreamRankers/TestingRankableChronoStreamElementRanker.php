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

namespace Test\Tumblr\StreamBuilder\StreamRankers;

use Test\Tumblr\StreamBuilder\StreamElements\TestingRankableChronoStreamElement;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function usort;
use function get_class;

/**
 * A ranker for chrono stream elements
 */
final class TestingRankableChronoStreamElementRanker extends StreamRanker
{
    /**
     * @inheritDoc
     */
    protected function rank_inner(array $stream_elements, StreamTracer $tracer = null): array
    {
        usort($stream_elements, function (TestingRankableChronoStreamElement $a, TestingRankableChronoStreamElement $b) {
            return $b->get_rank_ordinal() <=> $a->get_rank_ordinal();
        });
        return $stream_elements;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [ '_type' => get_class($this) ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_current_identity());
    }

    /**
     * @inheritDoc
     */
    protected function pre_fetch(array $elements)
    {
        // no need to do anything
    }
}
