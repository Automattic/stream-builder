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

namespace Tumblr\StreamBuilder\StreamCursors;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * This represents the combination pagination state of a filter and an inner stream.
 * FilteredStreamCursor is the dedicated cursor for FilteredStream.
 */
final class FilteredStreamCursor extends StreamCursor
{
    /** @var StreamCursor */
    private $cursor;
    /** @var StreamFilterState|null Filter state, if any. */
    private $filter_state;

    /**
     * FilteredStreamCursor constructor.
     * @param StreamCursor|null $cursor The inner cursor tracking pagination state of the inner stream to be filtered.
     * @param StreamFilterState|null $filter_state The filter state.
     */
    public function __construct(StreamCursor $cursor = null, StreamFilterState $filter_state = null)
    {
        $this->cursor = $cursor;
        $this->filter_state = $filter_state;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $output = ['_type' => get_class($this)];
        if ($this->cursor instanceof StreamCursor) {
            $output['c'] = $this->cursor->to_template();
        }
        if ($this->filter_state instanceof StreamFilterState) {
            $output['f'] = $this->filter_state->to_template();
        }
        return $output;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $inner_cursor_template = Helpers::idx2($template, 'c', 'cursor');
        $cursor = $inner_cursor_template ? StreamSerializer::from_template($context->derive($inner_cursor_template, 'cursor')) : null;

        $filter_state_template = Helpers::idx2($template, 'f', 'filter_state');
        $filter_state = $filter_state_template ? StreamSerializer::from_template($context->derive($filter_state_template, 'filter_state')) : null;

        return new self($cursor, $filter_state);
    }

    /**
     * Create an equivalent cursor using the provided filter state.
     * @param StreamFilterState|null $filter_state The filter state.
     * @return FilteredStreamCursor
     */
    public function with_filter_state(StreamFilterState $filter_state = null): FilteredStreamCursor
    {
        return new self($this->cursor, $filter_state);
    }

    /**
     * @param StreamElement $el The stream element to be combined with.
     * @return StreamCursor
     */
    public function combine_from(StreamElement $el): StreamCursor
    {
        return $this->_combine_with(new FilteredStreamCursor(
            $el->get_cursor()
        ));
    }

    /**
     * To get the inner source stream cursor.
     * @return null|StreamCursor
     */
    public function get_inner_cursor()
    {
        return $this->cursor;
    }

    /**
     * To get the filter state.
     * @return StreamFilterState|null
     */
    public function get_filter_state()
    {
        return $this->filter_state;
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var FilteredStreamCursor $other */
        return new FilteredStreamCursor(
            StreamCursor::combine_all([$this->cursor, $other->cursor]),
            StreamFilterState::merge_all([$this->filter_state, $other->filter_state])
        );
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return (($other instanceof FilteredStreamCursor)
            && (is_null($this->cursor) || $this->cursor->can_combine_with($other->cursor))
            && (is_null($this->filter_state) || $this->filter_state->can_merge_with($other->filter_state)));
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('Filtered(%s,%s)', $this->cursor, $this->filter_state);
    }
}
