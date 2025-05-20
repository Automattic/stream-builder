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

/**
 * A cursor for the search stream.
 */
class SearchStreamCursor extends StreamCursor
{
    /** @var int */
    private $offset;

    /**
     * @var bool
     * Recent search implies $offset represents timestamp
     */
    private $recent_search;

    /**
     * @param int $offset The search response offset
     * @param bool $recent_search If it is recent search cursor
     * @throws \InvalidArgumentException If offset is less than zero.
     */
    public function __construct(int $offset, bool $recent_search = false)
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException("Offset should be a greater than or equal to zero. Input: " . $offset);
        }
        $this->offset = $offset;
        $this->recent_search = $recent_search;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => self::class,
            'o' => $this->offset,
            'chrono' => $this->recent_search,
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        return new self(
            Helpers::idx2($template, 'o', 'offset'),
            Helpers::idx2($template, 'chrono', 'chronological', false)
        );
    }

    /**
     * @return int
     */
    public function get_offset(): int
    {
        return $this->offset;
    }

    /**
     * @inheritDoc
     * Wrapped in __toString() method
     */
    #[\Override]
    protected function to_string(): string
    {
        return sprintf('SearchStreamCursor(%d, %d)', $this->offset, intval($this->recent_search));
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return ($other instanceof SearchStreamCursor && $other->recent_search === $this->recent_search);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var SearchStreamCursor $other */
        if ($this->recent_search) {
            return $this->offset > $other->offset ? $other : $this;
        } else {
            return $this->offset > $other->offset ? $this : $other;
        }
    }
}
