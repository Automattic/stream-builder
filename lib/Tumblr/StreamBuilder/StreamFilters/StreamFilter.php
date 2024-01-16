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

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\Templatable;

/**
 * A predicate usable in filtering streams.
 */
abstract class StreamFilter extends Templatable
{
    /**
     * Filter header for logging and debugging purposes.
     */
    public const LOGGING_HEADER = 'filter_details';

    /**
     * @return string|null A string which uniquely identifies this filter, used for caching.
     * Return null when this filter is known to not be cacheable.
     */
    abstract public function get_cache_key();

    /**
     * @return string|null A string used to manage this filter's individual output state in the
     * context of a {@see CompositeStreamFilterState}. Return null when this filter does not maintain
     * state (this is the default, because it is so common)
     */
    public function get_state_id()
    {
        return null;
    }

    /**
     * We override __toString method, and proxy to a default to_string() implementation.
     * This method should return a human friendly string to represent this StreamFilter, it's NOT necessary to be identical.
     * @return string
     */
    final public function __toString(): string
    {
        return $this->to_string();
    }

    /**
     * Default implementation of to_string.
     * Override this if you want a more descriptive name of this StreamFilter.
     * @return string
     */
    protected function to_string(): string
    {
        return Helpers::get_unqualified_class_name($this);
    }

    /**
     * Filter stream elements, this method will trace filter process, and proxy the concrete filter implementation
     * to the filter_inner method.
     * @param StreamElement[] $elements The candidate StreamElement instances to filter.
     * @param StreamFilterState $state The filter state passed from previous filter operations.
     * @param StreamTracer|null $tracer The tracer passed in to track filter behaviors.
     * @throws \Exception Rethrown exceptions from filter_inner call.
     * @return StreamFilterResult
     */
    final public function filter(
        array $elements,
        ?StreamFilterState $state = null,
        ?StreamTracer $tracer = null
    ): StreamFilterResult {
        if (!$this->can_filter()) {
            $tracer && $tracer->filter_skip($this);
            return new StreamFilterResult($elements, []);
        }
        $t0 = microtime(true);
        $size = count($elements);
        if ($tracer) {
            $tracer->begin_filter($this, $size);
        }
        try {
            $filtered = $this->filter_inner($elements, $state, $tracer);
        } catch (\Exception $e) {
            if ($tracer) {
                $tracer->fail_filter($this, $size, [$t0, microtime(true) - $t0], $e);
            }
            throw $e;
        }
        // Trace element release by a certain leaf filter.
        foreach ($filtered->get_released() as $element) {
            if (!isset($element->get_debug_info()[self::LOGGING_HEADER][StreamTracer::META_FILTER_CODE])) {
                $element->add_debug_info(
                    self::LOGGING_HEADER,
                    StreamTracer::META_FILTER_CODE,
                    Helpers::get_unqualified_class_name($this)
                );
            }
        }
        if ($tracer) {
            $tracer->end_filter(
                $this,
                $filtered->get_released_count(),
                [$t0, microtime(true) - $t0]
            );
            foreach ($filtered->get_released() as $element) {
                $tracer->release_element($this, $element);
            }
            if (count($elements) === $filtered->get_released_count()) {
                $tracer->release_all_elements($this, $filtered->get_released_count());
            }
        }
        return $filtered;
    }

    /**
     * The filter concrete logic implementation.
     * @param StreamElement[] $elements The candidate StreamElement instances to filter.
     * @param StreamFilterState $state The filter state passed from previous filter operations.
     * @param StreamTracer|null $tracer The tracer passed in to track filter behaviors.
     * @return StreamFilterResult
     */
    abstract protected function filter_inner(array $elements, StreamFilterState $state = null, StreamTracer $tracer = null): StreamFilterResult;

    /**
     * Whether this filter is enabled. Default to true.
     * @return bool
     */
    protected function can_filter(): bool
    {
        return true;
    }
}
