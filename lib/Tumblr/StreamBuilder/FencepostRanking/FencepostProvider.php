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

namespace Tumblr\StreamBuilder\FencepostRanking;

/**
 * Thing that provides fenceposts, obviously.
 */
abstract class FencepostProvider
{
    /**
     * Get the timestamp of the latest fencepost in a fence.
     * @param string $fence_id The fence identity, typically includes the identity
     * of the templated ranker and the user id.
     * @return int|null
     */
    abstract public function get_latest_timestamp(string $fence_id);

    /**
     * Set the latest fencepost timestamp for a fence.
     * @param string $fence_id The fence id.
     * @param int $timestamp_ms The timestamp of the latest fencepost.
     * @throws \InvalidArgumentException If the timestamp is negative.
     * @return void
     */
    abstract public function set_latest_timestamp(string $fence_id, int $timestamp_ms);

    /**
     * Get the latest fencepost in a fence.
     * @param string $fence_id The fence identity, typically includes the identity
     * of the templated ranker and the user id.
     * @return Fencepost|null
     */
    final public function get_latest_fencepost(string $fence_id)
    {
        $latest_ts = $this->get_latest_timestamp($fence_id);
        if (is_null($latest_ts)) {
            return null;
        }
        return $this->get_fencepost($fence_id, $latest_ts);
    }

    /**
     * Get a specific fencepost in a fence.
     * @param string $fence_id The fence identity, typically includes the identity
     * of the templated ranker and the user id.
     * @param int $timestamp_ms The timestamp of the fencepost to retrieve.
     * @return Fencepost|null
     */
    abstract public function get_fencepost(string $fence_id, int $timestamp_ms);

    /**
     * Set a specific fencepost of a fence.
     * @param string $fence_id The fence identity, typically includes the identity
     * of the templated ranker and the user id.
     * @param int $timestamp_ms The timestamp of the fencepost to store.
     * @param Fencepost $fencepost The fencepost to store.
     * @throws \InvalidArgumentException If the timestamp is negative.
     * @return void
     */
    abstract public function set_fencepost(string $fence_id, int $timestamp_ms, Fencepost $fencepost);

    /**
     * Set the latest fencepost in a fence.
     * @param string $fence_id The fence identity, typically includes the identity
     * of the templated ranker and the user id.
     * @param int $timestamp_ms The timestamp of the fencepost to store.
     * @param Fencepost $fencepost The fencepost to store.
     * @throws \InvalidArgumentException If the timestamp is negative.
     * @return void
     */
    final public function set_latest_fencepost(string $fence_id, int $timestamp_ms, Fencepost $fencepost)
    {
        if ($timestamp_ms < 0) {
            throw new \InvalidArgumentException('Timestamp must be non-negative');
        }
        $this->set_fencepost($fence_id, $timestamp_ms, $fencepost);
        $this->set_latest_timestamp($fence_id, $timestamp_ms);
    }

    /**
     * Sets the last valid time for a user's fencepost.
     * All fenceposts before the given timestamp_ms are invalid.
     * Each user-epoch pair is valid for all contexts.
     * @param string $user_id The user id as string for which fenceposts are no longer valid.
     * @param int $timestamp_ms The timestamp from when the fenceposts are no longer valid.
     * @return void
     */
    abstract public function set_fencepost_epoch(string $user_id, int $timestamp_ms);

    /**
     * Gets the last valid epoch (timestamp in ms) for a given user.
     * If there is no epoch (null), all the fenceposts are still valid for that user.
     * If there is an existing epoch, only fenceposts after that timestamp are valid,
     * the ones before should not be served.
     * @param string $user_id User id as string.
     * @return int|null timestamp in ms, or null.
     */
    abstract public function get_fencepost_epoch(string $user_id);
}
