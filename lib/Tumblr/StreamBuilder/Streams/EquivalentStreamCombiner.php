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

namespace Tumblr\StreamBuilder\Streams;

use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * An EquivalentStreamCombiner combines multiple streams that are equivalent to each other -- which means they return
 * the same element types AND those elements have cursors that are combinable even across source streams. Therefore
 * this does not need a multicursor, it can just combine all cursors directly without worrying about which source(s)
 * provided which cursor(s).
 */
abstract class EquivalentStreamCombiner extends Stream
{
    /**
     * @inheritDoc
     */
    final protected function _enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        /** @var StreamResult $combined_result */
        $combined_result = $this->combine($count, $cursor, $tracer, $option);
        return $combined_result->derive_all($this);
    }

    /**
     * Combines stream elements from its inner streams.
     * @param int $count How many slots need to be filled.
     * @param StreamCursor|null $cursor The cursor for mixing.
     * @param StreamTracer|null $tracer The tracer traces mix process.
     * @param EnumerationOptions|null $option The option for enumeration
     * @return StreamResult
     */
    abstract protected function combine(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult;
}
