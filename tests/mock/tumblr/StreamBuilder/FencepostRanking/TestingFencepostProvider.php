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

/**
 * Dummy FencepostProvider for testing fencepost ranking.
 */
final class TestingFencepostProvider extends FencepostProvider
{
    /** @var string */
    private $fence_id;
    /** @var int[] stores timestamps for each user: $epoch[user_id] = ts */
    private $epoch_cutoff;
    /** @var int|null */
    private $latest_timestamp_ms;
    /** @var Fencepost[] */
    private $fenceposts;

    /**
     * @param string $fence_id The fence id for which this provider will accept commands.
     * @param int|null $latest_timestamp_ms The head fencepost id
     * @param Fencepost[] $fenceposts The fenceposts as timestamp => Fencepost mapping
     */
    public function __construct(
        string $fence_id,
        int $latest_timestamp_ms = null,
        array $fenceposts = []
    ) {
        $this->fence_id = $fence_id;
        $this->latest_timestamp_ms = $latest_timestamp_ms;
        $this->fenceposts = $fenceposts;
        $this->epoch_cutoff = [];
    }

    /**
     * @inheritDoc
     */
    public function get_latest_timestamp(string $fence_id)
    {
        if ($fence_id !== $this->fence_id) {
            throw new \LogicException('Wrong fence');
        }
        return $this->latest_timestamp_ms;
    }

    /**
     * @inheritDoc
     */
    public function set_latest_timestamp(string $fence_id, int $timestamp_ms)
    {
        if ($fence_id !== $this->fence_id) {
            throw new \LogicException('Wrong fence');
        }
        $this->latest_timestamp_ms = $timestamp_ms;
    }

    /**
     * @inheritDoc
     */
    public function get_fencepost(string $fence_id, int $timestamp_ms)
    {
        if ($fence_id !== $this->fence_id) {
            throw new \LogicException('Wrong fence');
        }
        return $this->fenceposts[$timestamp_ms] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function set_fencepost(string $fence_id, int $timestamp_ms, Fencepost $fencepost)
    {
        if ($fence_id !== $this->fence_id) {
            throw new \LogicException('Wrong fence');
        }
        $this->fenceposts[$timestamp_ms] = $fencepost;
    }

    /**
     * @inheritDoc
     */
    public function set_fencepost_epoch(string $user_id, int $timestamp_ms)
    {
        $this->epoch_cutoff[$user_id] = $timestamp_ms;
    }

    /**
     * @inheritDoc
     */
    public function get_fencepost_epoch(string $user_id)
    {
        return $this->epoch_cutoff[$user_id] ?? null;
    }
}
