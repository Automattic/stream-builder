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
 * The stream interface for an enumerable source of elements which exposes opaque internal state through cursors.
 */
interface StreamInterface
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
    public function enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult;

    /**
     * @return int|null Positive numbers are an estimate of the stream length, if enumerated until exhaustion.
     * Negative numbers mean infinite.
     * Zero is Zero.
     * Null means unknown.
     */
    public function estimate_count(): ?int;

    /**
     * @return bool Is this component been skipped.
     */
    public function isSkippedComponent(): bool;

    /**
     * @param bool $isSkipped Mark this component skipped or not.
     * @return void
     */
    public function setSkippedComponent(bool $isSkipped): void;

    /**
     * @return string|null
     */
    public function getComponent(): ?string;

    /**
     * @param string|null $component The component.
     * @return void
     */
    public function setComponent(?string $component): void;

    /**
     * Convert an object to a template.
     * @return array A serialized representative template for an object.
     */
    public function to_template(): array;

    /**
     * @param bool $class_name If you want to append class name at the end of an identity.
     * Identity becomes 'template_name/stream_a/stream_b[StreamB]'
     * @return string The identity.
     */
    public function get_identity(bool $class_name = false): string;

    /**
     * Get the string representation of the current stream.
     * @return string
     */
    public function __toString(): string;
}
