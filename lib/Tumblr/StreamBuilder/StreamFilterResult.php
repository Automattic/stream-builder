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

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use function count;
use function sprintf;
use function implode;

/**
 * A structure representing the result of a filtering operation, containing both the elements that were
 * retained and those that were released.
 */
final class StreamFilterResult
{
    /**
     * @var StreamElement[]
     */
    private $retained;

    /**
     * @var StreamElement[]
     */
    private $released;

    /**
     * @var StreamFilterState[]
     */
    private $filter_states;

    /**
     * @param StreamElement[] $retained The sequence of StreamElement objects which passed the filter,
     * in the same order as input.
     * @param StreamElement[] $released The set of StreamElement objects which did not pass the filter,
     * in the same order as input.
     * @param StreamFilterState[] $filter_states Mapping from element cache keys to filter states. Note that the
     * elements in this array should refer to both the retained and released items! This is because someone could
     * theoretically wrap your filter in an e.g. {@see InverseFilter}, which would actually retain only
     * the released items, and therefore the merging of states needs to work on elements from both sets.
     * @throws TypeMismatchException If any parameter array contains elements of an incorrect type.
     */
    public function __construct(array $retained, array $released, array $filter_states = [])
    {
        foreach ($retained as $e) {
            if (!($e instanceof StreamElement)) {
                throw new TypeMismatchException(StreamElement::class, $e);
            }
        }
        foreach ($released as $e) {
            if (!($e instanceof StreamElement)) {
                throw new TypeMismatchException(StreamElement::class, $e);
            }
        }
        foreach ($filter_states as $fs) {
            if (!($fs instanceof StreamFilterState)) {
                throw new TypeMismatchException(StreamFilterState::class, $fs);
            }
        }
        $this->retained = $retained;
        $this->released = $released;
        $this->filter_states = $filter_states;
    }

    /**
     * @return StreamElement[] Elements which passed the filter, in the same order as were provided to the filter.
     */
    public function get_retained(): array
    {
        return $this->retained;
    }

    /**
     * @return StreamElement[] Elements which failed the filter, in the same order as were provided to the filter.
     */
    public function get_released(): array
    {
        return $this->released;
    }

    /**
     * @return int The number of elements that were retained by the filter application.
     */
    public function get_retained_count(): int
    {
        return count($this->retained);
    }

    /**
     * @return int The number of elements that were released by the filter application.
     */
    public function get_released_count(): int
    {
        return count($this->released);
    }

    /**
     * Get the filter state for a specific element.
     * @param StreamElement $element The element for which to retrieve filter state.
     * @return null|StreamFilterState
     */
    public function get_filter_state(StreamElement $element)
    {
        return $this->filter_states[$element->get_cache_key()] ?? null;
    }
    /**
     * To get the inverse stream filter result, it will basically unwrap stream elements from released elements, and
     * wrap retained stream elements into released elements.
     * @return self The inverse result, having swapped sets of retained and released elements.
     */
    public function get_inverse(): self
    {
        return self::create_from_leaf_filter(
            $this->released,
            $this->retained,
            $this->filter_states
        );
    }

    /**
     * @return StreamFilterState[]
     */
    public function get_filter_states(): array
    {
        return $this->filter_states;
    }

    /**
     * @return self An empty result, with neither retained nor released elements.
     */
    public static function create_empty(): self
    {
        return new self([], []);
    }

    /**
     * Helper method to generate StreamFilterResult, call this method if you are a leaf StreamFilter.
     * However you can also choose to form ReleasedStreamElement by yourself and call the public constructor directly.
     * NOTE: especially, CompositeStreamFilter __SHOULD NOT__ use this method!
     *
     * @param array $retained The sequence of StreamElement objects which passed the filter, in the same order as input.
     * @param array $released The set of StreamElement objects which did not pass the filter, in the same order as input.
     * @param array $filter_states Mapping from element cache keys to filter states. Note that the
     * elements in this array should refer to both the retained and released items! This is because someone could
     * theoretically wrap your filter in an e.g. {@see InverseFilter}, which would actually retain only
     * the released items, and therefore the merging of states needs to work on elements from both sets.
     * @return StreamFilterResult|static
     */
    public static function create_from_leaf_filter(
        array $retained,
        array $released,
        array $filter_states = []
    ): self {
        return new self($retained, $released, $filter_states);
    }

    /**
     * Get the string representation of the current StreamFilterResult.
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(retained:%s  released:%s  filter_states:%s)',
            Helpers::get_unqualified_class_name($this),
            implode(',', $this->retained),
            implode(',', $this->released),
            Helpers::json_encode($this->filter_states)
        );
    }
}
