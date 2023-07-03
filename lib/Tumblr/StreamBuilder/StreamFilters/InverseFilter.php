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

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A filter which inverts another filter. That is, swaps the retained and released sets.
 * The events traced by this filter is using InverseFilter as sender_type instead of inner filter.
 */
final class InverseFilter extends StreamFilter
{
    /**
     * @var StreamFilter
     */
    private $inner;

    /**
     * @param string $identity String identifying this element in the context of a stream topology.
     * @param StreamFilter $inner The filter to invert.
     */
    public function __construct(string $identity, StreamFilter $inner)
    {
        parent::__construct($identity);
        $this->inner = $inner;
    }

    /**
     * @inheritDoc
     */
    protected function can_filter(): bool
    {
        return $this->inner->can_filter();
    }

    /**
     * @inheritDoc
     */
    public function get_cache_key()
    {
        return sprintf('Inverse(%s)', $this->inner->get_cache_key());
    }

    /**
     * @inheritDoc
     */
    public function to_string(): string
    {
        return sprintf('Inverse(%s)', $this->inner);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'stream_filter' => $this->inner->to_template(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $filter = $context->deserialize_required_property('stream_filter');
        return new self($context->get_current_identity(), $filter);
    }

    /**
     * @inheritDoc
     */
    final public function filter_inner(array $elements, StreamFilterState $state = null, StreamTracer $tracer = null): StreamFilterResult
    {
        return $this->inner->filter($elements, $state, $tracer)->get_inverse();
    }
}
