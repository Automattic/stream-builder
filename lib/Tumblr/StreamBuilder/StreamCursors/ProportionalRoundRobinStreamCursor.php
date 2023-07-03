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

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * Cursor used by the ProportionalRoundRobinStream.
 */
final class ProportionalRoundRobinStreamCursor extends StreamCursor
{
    /**
     * The total number of elements returned from this stream before this cursor.
     * @var int
     */
    private $begin_index;

    /**
     * The next cursor for the major stream.
     * @var StreamCursor|null
     */
    private $major_stream_cursor;

    /**
     * Indexed by minor stream index, cursors for each minor stream.
     * @var StreamCursor[]
     */
    private $minor_stream_cursors;

    /**
     * @param int $begin_index The total number of elements returned from this stream before this cursor.
     * @param StreamCursor|null $major_stream_cursor The next cursor for the major stream.
     * @param StreamCursor[] $minor_stream_cursors Indexed by minor stream index, cursors for each minor stream.
     * @throws TypeMismatchException If some minor stream cursor is invalid.
     */
    public function __construct(
        int $begin_index,
        ?StreamCursor $major_stream_cursor,
        array $minor_stream_cursors
    ) {
        $this->begin_index = $begin_index;
        $this->major_stream_cursor = $major_stream_cursor;
        foreach ($minor_stream_cursors as $minor_stream_cursor) {
            if (!($minor_stream_cursor instanceof StreamCursor)) {
                throw new TypeMismatchException(StreamCursor::class, $minor_stream_cursor);
            }
        }
        ksort($minor_stream_cursors);
        $this->minor_stream_cursors = $minor_stream_cursors;
    }

    /**
     * Getter method for get_begin_index
     * @return int
     */
    public function get_begin_index(): int
    {
        return $this->begin_index;
    }

    /**
     * Getter method for major_stream_cursor
     * @return StreamCursor|null
     */
    public function get_major_stream_cursor()
    {
        return $this->major_stream_cursor;
    }

    /**
     * Get the cursor for a minor stream.
     * @param int $idx The index of the minor stream.
     * @return StreamCursor|null
     */
    public function get_minor_stream_cursor(int $idx)
    {
        return $this->minor_stream_cursors[$idx] ?? null;
    }

    /**
     * Get the cursors for all minor streams.
     * @return StreamCursor[]
     */
    public function get_minor_stream_cursors(): array
    {
        return $this->minor_stream_cursors;
    }

    /**
     * Return a new cursor that represents the state of this cursor,
     * along with the given element having been taken from the given minor stream.
     * @param StreamElement $elem The element from the minor stream
     * @param int $minor_stream_index The index of the minor stream
     * @param int $element_index The zero-based index of the element being returned from the proportional stream.
     * @return ProportionalRoundRobinStreamCursor
     */
    public function derive_minor(StreamElement $elem, int $minor_stream_index, int $element_index): self
    {
        $minor_cursors = $this->minor_stream_cursors;
        $minor_cursors[$minor_stream_index] = StreamCursor::combine_all([
            $elem->get_cursor(),
            $this->get_minor_stream_cursor($minor_stream_index),
        ]);
        /** @var StreamCursor[] $minor_cursors */
        return new self(
            $this->begin_index + 1 + $element_index,
            $this->major_stream_cursor,
            $minor_cursors
        );
    }

    /**
     * Return a new cursor that represents the state of this cursor,
     * along with the given element having been taken from the major stream.
     * @param StreamElement $elem The element from the major stream
     * @param int $element_index The zero-based index of the element being returned from the proportional stream.
     * @return ProportionalRoundRobinStreamCursor
     */
    public function derive_major(StreamElement $elem, int $element_index): self
    {
        return new self(
            $this->begin_index + 1 + $element_index,
            StreamCursor::combine_all([
                $elem->get_cursor(),
                $this->major_stream_cursor,
            ]),
            $this->minor_stream_cursors
        );
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return ($other instanceof ProportionalRoundRobinStreamCursor);
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var ProportionalRoundRobinStreamCursor $other */
        $combined_minor_cursors = $this->minor_stream_cursors;
        foreach ($other->minor_stream_cursors as $index => $other_cursor) {
            if (isset($combined_minor_cursors[$index])) {
                $combined_minor_cursors[$index] = $combined_minor_cursors[$index]->combine_with($other_cursor);
            } else {
                $combined_minor_cursors[$index] = $other_cursor;
            }
        }

        return new self(
            max($this->begin_index, $other->begin_index),
            StreamCursor::combine_all([$this->major_stream_cursor, $other->major_stream_cursor]),
            $combined_minor_cursors
        );
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        $minors = [];
        foreach ($this->minor_stream_cursors as $minor_stream_idx => $minor_stream_cursor) {
            $minors[] = sprintf('%d:%s', $minor_stream_idx, $minor_stream_cursor->to_string());
        }
        return sprintf(
            'PropRobin(%d,%s,%s)',
            $this->begin_index,
            $this->major_stream_cursor,
            sprintf('[%s]', implode(',', $minors))
        );
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = [
            '_type' => get_class($this),
            'i' => $this->begin_index,
        ];

        if ($this->major_stream_cursor instanceof StreamCursor) {
            $base['m'] = $this->major_stream_cursor->to_template();
        }

        $minors = [];
        foreach ($this->minor_stream_cursors as $idx => $cur) {
            if ($cur instanceof StreamCursor) {
                $minors[$idx] = $cur->to_template();
            } else {
                $minors[$idx] = null;
            }
        }

        $base['n'] = $minors;
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $minors = [];
        foreach ($template['n'] as $idx => $minor_template) {
            if (is_null($minor_template)) {
                $minors[$idx] = null;
            } else {
                $minors[$idx] = StreamSerializer::from_template(
                    $context->derive($minor_template, sprintf('n/%d', $idx))
                );
            }
        }

        $major = null;
        if ($major_template = $template['m'] ?? null) {
            $major = StreamSerializer::from_template($context->derive($major_template, 'major'));
        }

        return new self(
            intval($template['i'] ?? "0"),
            $major,
            $minors
        );
    }

    /**
     * Make an empty cursor, equivalent to the first page.
     * @return ProportionalRoundRobinStreamCursor
     */
    public static function make_empty(): self
    {
        return new self(0, null, []);
    }
}
