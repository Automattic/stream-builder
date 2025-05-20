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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A filter which combines multiple filters, retaining only those elements which are retained by all of them.
 */
final class CompositeStreamFilter extends StreamFilter
{
    /** @var StreamFilter[] */
    private $filters;

    /**
     * @param string $identity String identifying this element in the context of a stream topology.
     * @param StreamFilter[] $filters The constituent filters.
     * @throws TypeMismatchException If some element of the array is not a StreamFilter.
     */
    public function __construct(string $identity, array $filters)
    {
        parent::__construct($identity);
        foreach ($filters as $f) {
            if (!($f instanceof StreamFilter)) {
                throw new TypeMismatchException(StreamFilter::class, $f);
            }
        }
        $this->filters = $filters;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function can_filter(): bool
    {
        foreach ($this->filters as $f) {
            if ($f->can_filter()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function get_cache_key()
    {
        $ids = [];
        foreach ($this->filters as $f) {
            /** @var StreamFilter $f */
            $inner_ck = $f->get_cache_key();
            if (is_null($inner_ck)) {
                return null; // composite containing an uncacheable filter is uncacheable!
            }
            $ids[] = $inner_ck;
        }
        return sprintf('Composite(%s)', implode(',', $ids));
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            "_type" => get_class($this),
            "stream_filter_array" => array_map(function ($fi) {
                /** @var StreamFilter $fi */
                return $fi->to_template();
            }, $this->filters),
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        $filters_template = $context->get_required_property('stream_filter_array');
        $filters = [];
        foreach ($filters_template as $i => $f_template) {
            $filter = StreamSerializer::from_template($context->derive($f_template, sprintf('stream_filter_array/%d', $i)));
            $filters[] = $filter;
        }
        return new self($context->get_current_identity(), $filters);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function filter_inner(array $elements, ?StreamFilterState $state = null, ?StreamTracer $tracer = null): StreamFilterResult
    {
        /** @var CompositeStreamFilterState $state */
        if (is_null($state)) {
            $state = new CompositeStreamFilterState([]);
        } elseif (!($state instanceof CompositeStreamFilterState)) {
            throw new TypeMismatchException(CompositeStreamFilterState::class, $state);
        }

        // assume all elements are good, and iterate through filters:

        /** @var StreamElement[] $retained */
        /** @var StreamElement[] $released */
        /** @var CompositeStreamFilterState[] $element_states */
        $retained = $elements;
        $released = [];
        $element_states = [];

        foreach ($this->filters as $f) {
            if (0 == count($retained)) {
                break;
            }
            $filter_result = $f->filter($retained, $state->state_for_filter($f), $tracer);

            // update retained and released subsets:
            $retained = $filter_result->get_retained();
            $released = array_merge($filter_result->get_released(), $released);

            if ($filter_id = $f->get_state_id()) {
                // this filter has a state id, and so therefore has managed state.
                foreach ($filter_result->get_filter_states() as $element_key => $element_filter_state) {
                    if (!is_null($element_filter_state)) {
                        $composite_element_filter_state = new CompositeStreamFilterState([
                            $filter_id => $element_filter_state,
                        ]);
                        if (isset($element_states[$element_key])) {
                            $tmp = $element_states[$element_key];
                            $element_states[$element_key] = $composite_element_filter_state->merge_with($tmp);
                        } else {
                            $element_states[$element_key] = $composite_element_filter_state;
                        }
                    }
                }
            }
        }
        return new StreamFilterResult($retained, $released, $element_states);
    }
}
