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

namespace Tumblr\StreamBuilder\StreamFilters;

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * The state of a composite filter containing at least one stateful constituent.
 */
class CompositeStreamFilterState extends StreamFilterState
{
    /** @var StreamFilterState[] */
    private $states;

    /**
     * @param StreamFilterState[] $states Mapping from stream filter ids to states (null is a valid state).
     * @throws TypeMismatchException If some element of the provided array is not a StreamFilterState or null.
     */
    public function __construct(array $states)
    {
        $nonempty_states = [];
        foreach ($states as $id => $state) {
            if ($state instanceof StreamFilterState) {
                $nonempty_states[$id] = $state;
            } elseif (!is_null($state)) {
                throw new TypeMismatchException(StreamFilterState::class, $state);
            }
        }
        $this->states = $nonempty_states;
    }

    /** @inheritDoc */
    protected function _can_merge_with(StreamFilterState $other): bool
    {
        return ($other instanceof CompositeStreamFilterState);
    }

    /** @inheritDoc */
    protected function _merge_with(StreamFilterState $other): StreamFilterState
    {
        /** @var CompositeStreamFilterState $other */
        $merged = $this->states;
        foreach ($other->states as $id => $state) {
            if (isset($merged[$id])) {
                $merged[$id] = $merged[$id]->merge_with($state);
            } else {
                $merged[$id] = $state;
            }
        }
        return new CompositeStreamFilterState($merged);
    }

    /**
     * Get the state for a specific filter.
     * @param StreamFilter $sf The filter for which to get state.
     * @return StreamFilterState|null
     */
    public function state_for_filter(StreamFilter $sf)
    {
        $filter_state_id = $sf->get_state_id();
        if (is_null($filter_state_id)) {
            // stateless filter
            return null;
        } else {
            return $this->states[$filter_state_id] ?? null;
        }
    }

    /** @inheritDoc */
    protected function to_string(): string
    {
        $inners = [];
        foreach ($this->states as $id => $state) {
            $inners[] = sprintf("%s:%s", $id, $state->to_string());
        }
        return sprintf('Composite(%s)', implode(',', $inners));
    }

    /** @inheritDoc */
    public function to_template(): array
    {
        $inner = [];
        foreach ($this->states as $id => $state) {
            $inner[$id] = $state->to_template();
        }
        return [
            '_type' => get_class($this),
            's' => $inner,
        ];
    }

    /** @inheritDoc */
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $states_template = Helpers::idx2($template, 's', 'states', []);
        $states = [];
        foreach ($states_template as $id => $state_template) {
            $states[$id] = StreamSerializer::from_template($context->derive($state_template, sprintf('states/%s', $id)));
        }
        return new self($states);
    }
}
