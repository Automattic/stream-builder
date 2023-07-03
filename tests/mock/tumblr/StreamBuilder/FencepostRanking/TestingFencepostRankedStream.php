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

namespace Tumblr\StreamBuilder\FencepostRanking;

use Tumblr\StreamBuilder\StreamContext;

/**
 * Dummy TestingFencepostRankedStream for testing fencepost ranking.
 */
final class TestingFencepostRankedStream extends FencepostRankedStream
{
    /**
     * @inheritDoc
     */
    protected function get_fence_id_str(): string
    {
        return 'test_fence_id';
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return parent::to_template();
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        new self(
            $context->deserialize_required_property('inner', 'chrono/inner'),
            $context->deserialize_required_property('head_ranker'),
            $context->get_required_property('head_count'),
            $context->get_optional_property('rank_seed', true),
            $context->get_current_identity(),
            new TestingFencepostProvider('test_fence_id')
        );
    }
}
