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

use Tumblr\StreamBuilder\Exceptions\UnmergeableFilterStateException;

/**
 * A StreamFilterState is like a cursor for a StreamFilter, allowing it to maintain its own state.
 * Just like a StreamCursor, it is only "merged" lazily, so only filter state for elements that are
 * ultimately returned are used in building the next state.
 */
abstract class StreamFilterState extends Templatable
{
    /**
     * Test if this filter state can be merged with the provided filter state.
     * @param StreamFilterState|null $other The other state.
     * @return bool True, iff the states can merge.
     */
    final public function can_merge_with(?StreamFilterState $other = null): bool
    {
        return is_null($other) || $this->_can_merge_with($other);
    }

    /**
     * Test if this filter state can be merged with the provided filter state.
     * @param StreamFilterState $other The other state, never null.
     * @return bool True, iff the states can merge.
     */
    abstract protected function _can_merge_with(StreamFilterState $other): bool;

    /**
     * Combine two states, yielding the state representing their aggregate.
     * @param StreamFilterState|null $other The state with which to combine this state.
     * @return StreamFilterState The combined state.
     * @throws UnmergeableFilterStateException If the provided state cannot be merged with this state.
     */
    final public function merge_with(?StreamFilterState $other = null): StreamFilterState
    {
        if (is_null($other)) {
            return $this;
        } elseif ($this->can_merge_with($other)) {
            return $this->_merge_with($other);
        } else {
            throw new UnmergeableFilterStateException($this, $other);
        }
    }

    /**
     * Combine two states, yielding the state representing their aggregate.
     * @param StreamFilterState $other The state with which to combine this state, already validated
     * by can_merge_with and never null.
     * @return StreamFilterState The combined state.
     * @throws UnmergeableFilterStateException If the provided state cannot be merged with this state.
     */
    abstract protected function _merge_with(StreamFilterState $other): StreamFilterState;

    /**
     * We override __toString and call the abstract to_string method, which FORCES implementors to implement
     * some form of stringification. Otherwise, we cant force everyone to do it...
     * @return string The string representation of this filter state, for logging purposes.
     */
    final public function __toString(): string
    {
        return $this->to_string();
    }

    /**
     * @return string A string representation of this filter state, for logging purposes. The
     * representation should be human-readable and not necessarily unique, etc, etc.
     */
    abstract protected function to_string(): string;

    /**
     * Merge all the provided filter states.
     * @param array<StreamFilterState|null> $filter_states The filter states to merge.
     * @return StreamFilterState|null
     */
    public static function merge_all(array $filter_states)
    {
        $result = null;
        foreach ($filter_states as $fs) {
            if (!is_null($fs)) {
                $result = $fs->merge_with($result);
            }
        }
        return $result;
    }
}
