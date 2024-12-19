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
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\Templatable;

/**
 * An enumerable source of elements which exposes opaque internal state through cursors.
 */
abstract class Stream extends Templatable implements StreamInterface
{
    /**
     * Enumerate items from this stream.
     * @param int $count The number of results to enumerate.
     * @param StreamCursor|null $cursor The cursor (state) from which to resume.
     * @param StreamTracer|null $tracer The tracer into which events should be logged.
     * @param EnumerationOptions|null $option The option on enumeration
     * @return StreamResult The result of the enumeration.
     * @throws \RuntimeException If enumeration is impossible for any reason.
     * @throws \InvalidArgumentException If the requested count is less or equal to 0.
     */
    final public function enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $t0 = microtime(true);
        if ($count <= 0) {
            $tracer && $tracer->skip_enumerate($this, $count, $cursor);
            throw new \InvalidArgumentException(
                sprintf('Count should be positive for enumeration on stream. Incoming: %d', $count)
            );
        }

        if (!$this->can_enumerate()) {
            $tracer && $tracer->skip_enumerate($this, $count, $cursor);
            return new StreamResult(true, []);
        }

        $tracer && $tracer->begin_enumerate($this, $count, $cursor);
        try {
            $result = $this->_enumerate($count, $cursor, $tracer, $option);
            array_map(function (StreamElement $e) {
                if ($e->getComponent() === null) {
                    // do not override inner component tag
                    $e->setComponent($this->getComponent());
                }
                return $e;
            }, $result->get_elements());
        } catch (\RuntimeException $e) {
            $tracer && $tracer->fail_enumerate($this, $count, $cursor, [$t0, microtime(true) - $t0], $e);
            throw $e;
        }
        $tracer && $tracer->end_enumerate($this, $result->get_size(), $result, [$t0, microtime(true) - $t0]);
        return $result;
    }

    /**
     * Enumerate items from this stream. DO NOT CALL THIS METHOD, RATHER CALL 'enumerate()'
     * @param int $count The number of results to enumerate.
     * @param StreamCursor|null $cursor The cursor (state) from which to resume.
     * @param StreamTracer|null $tracer The tracer into which events should be logged.
     * @param EnumerationOptions|null $option The option on enumeration
     * @return StreamResult The result of the enumeration.
     */
    abstract protected function _enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult;

    /**
     * Indicate if you are eligible for enumerate stream results.
     * Override this if you have customized logic to determine can_enumerate.
     * @return bool Default to be true.
     */
    protected function can_enumerate(): bool
    {
        return !$this->isSkippedComponent();
    }

    /**
     * Indicate if the stream can be enumerate with a timestamp range
     * Default to false, leaf stream need to implement specific time range enumeration logic before setting this to true
     * Combination streams e.g FilteredStream, ConcatenatedStream, StreamMixer depends on inner stream
     * @return bool Default to be false
     */
    protected function can_enumerate_with_time_range(): bool
    {
        return false;
    }

    /**
     * @return int|null Positive numbers are an estimate of the stream length, if enumerated until exhaustion.
     * Negative numbers mean infinite.
     * Zero is Zero.
     * Null means unknown.
     */
    public function estimate_count(): ?int
    {
        // TODO go to streams and combiners etc used on blog pages and implement this method.
        return null;
    }

    /**
     * Get the string representation of the current stream.
     * @return string
     */
    final public function __toString(): string
    {
        return $this->to_string();
    }

    /**
     * Proxyed by __toString().
     * Default implementation is provided.
     * Override this if you want a more descriptive name.
     * @return string
     */
    protected function to_string(): string
    {
        return Helpers::get_unqualified_class_name($this);
    }
}
