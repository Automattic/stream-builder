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
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Essentially just a StreamMixer without the injection logic.
 */
abstract class StreamCombiner extends Stream
{
    /**
     * @inheritDoc
     */
    final protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        if (is_null($cursor)) {
            $cursor = new MultiCursor([]);
        } elseif (!($cursor instanceof MultiCursor)) {
            throw new InappropriateCursorException($this, $cursor);
        }

        /** @var StreamResult $combined_result */
        $combined_result = $this->combine($count, $cursor, $tracer, $option);

        // Maps each StreamElement to a DerivedStreamElement.
        /** @var StreamElement[] $derived_elements */
        $derived_elements = [];
        foreach ($combined_result->get_elements() as $element) {
            /** @var StreamElement $element */
            $derived_elements[] = new DerivedStreamElement(
                $element,
                $this->get_identity(),
                $cursor->combine_from($element)
            );
        }

        return new StreamResult(
            $combined_result->is_exhaustive(),
            $derived_elements
        );
    }

    /**
     * Combines stream elements from its inner streams.  Distribute MultiCursor
     * on each element.
     * @param int $count How many slots need to be filled.
     * @param MultiCursor $cursor The cursor for mixing
     * @param StreamTracer|null $tracer The tracer traces mix process.
     * @param EnumerationOptions|null $option The option for enumeration
     * @return StreamResult
     */
    abstract protected function combine(
        int $count,
        MultiCursor $cursor,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult;
}
