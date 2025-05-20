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

use InvalidArgumentException;
use Tumblr\StreamBuilder\StreamContext;

/**
 * The dedicated stream cursor for ConcatenatedStream.
 */
class ConcatenatedStreamCursor extends StreamCursor
{
    /** @var int concatenated stream index in ConcatenatedStream */
    private $source_index;

    /** @var StreamCursor concatenated stream's cursor */
    private $source_cursor;

    /**
     * @param int $source_index Index of the original stream in ConcatenatedStream
     * @param StreamCursor $source_cursor The original cursor of element
     * @throws \InvalidArgumentException If source index is not an int.
     */
    public function __construct(int $source_index, ?StreamCursor $source_cursor = null)
    {
        $this->source_index = $source_index;
        $this->source_cursor = $source_cursor;
    }

    /**
     * @return int concatenated stream index in ConcatenatedStream
     */
    public function get_source_index(): int
    {
        return $this->source_index;
    }

    /**
     * @return StreamCursor|null concatenated stream's cursor
     */
    public function get_source_cursor()
    {
        return $this->source_cursor;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _can_combine_with(StreamCursor $other): bool
    {
        if (!$other instanceof ConcatenatedStreamCursor) {
            return false;
        }
        if ($this->source_cursor === null) {
            return true;
        }
        if ($this->source_index != $other->source_index) {
            return true;
        }
        return $this->source_cursor->can_combine_with($other->source_cursor);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var ConcatenatedStreamCursor $other */
        if ($other->source_index > $this->source_index || empty($this->source_cursor)) {
            return $other;
        } elseif ($this->source_index > $other->source_index) {
            return $this;
        } else {
            return new ConcatenatedStreamCursor(
                $this->source_index,
                $this->source_cursor->combine_with($other->source_cursor)
            );
        }
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function to_string(): string
    {
        return sprintf('Concat(%d,%s)', $this->source_index, $this->source_cursor);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'i' => $this->source_index,
            'c' => is_null($this->source_cursor) ? null : $this->source_cursor->to_template(),
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        return new self($context->get_required_property('i'), $context->deserialize_optional_property('c'));
    }
}
