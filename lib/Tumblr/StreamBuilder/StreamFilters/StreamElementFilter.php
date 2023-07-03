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

use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Abstract filter which tests each element individually. If you are able to perform set operations,
 * you should instead implement StreamFilter directly.
 */
abstract class StreamElementFilter extends StreamFilter
{
    /**
     * @inheritDoc
     */
    final public function filter_inner(array $elements, StreamFilterState $state = null, StreamTracer $tracer = null): StreamFilterResult
    {
        $retained = [];
        $released = [];

        $this->pre_fetch($elements);
        foreach ($elements as $e) {
            if ($this->should_release($e)) {
                $released[] = $e;
            } else {
                $retained[] = $e;
            }
        }
        return StreamFilterResult::create_from_leaf_filter($retained, $released);
    }

    /**
     * A batch pre-fetch to inflate a set of stream elements or cache data need to be used in your filter.
     * @param StreamElement[] $elements The elements need to be pre_fetched.
     * @return void
     */
    abstract protected function pre_fetch(array $elements);

    /**
     * Test whether this filter should release the given element.
     * @param StreamElement $e The element to test.
     * @return bool True, if the element should be released.
     */
    abstract protected function should_release(StreamElement $e): bool;
}
