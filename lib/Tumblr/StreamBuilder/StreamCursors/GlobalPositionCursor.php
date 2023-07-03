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

use Tumblr\StreamBuilder\Exceptions\UncombinableCursorException;
use Tumblr\StreamBuilder\StreamContext;

/**
 * This is a wrapper for StreamCursor that keeps track of the position of the cursor.
 * Used to keep track of how many elements have been served between requests/pages.
 */
class GlobalPositionCursor extends StreamCursor
{
    /**
     * @var StreamCursor Combined cursor which is used for pagination.
     */
    private $inner_cursor;

    /**
     * @var int Global position of this cursor
     */
    private $global_position;

    /**
     * GlobalPositionCursor constructor.
     *
     * @param StreamCursor $inner_cursor Cursor that GlobalPositionCursor is wrapping
     * @param int $global_position The "global" or "absolute" position of this cursor
     */
    public function __construct(StreamCursor $inner_cursor, int $global_position)
    {
        $this->inner_cursor = $inner_cursor;
        $this->global_position = $global_position;
    }

    /**
     * @return int
     */
    public function getGlobalPosition(): int
    {
        return $this->global_position;
    }

    /**
     * @return StreamCursor
     */
    public function getInnerCursor(): StreamCursor
    {
        return $this->inner_cursor;
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        throw new UncombinableCursorException($this, $other);
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf(
            'GlobalPositionCursor(%s,%s)',
            $this->inner_cursor,
            $this->global_position
        );
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'o' => $this->inner_cursor->to_template(),
            'gp' => $this->global_position,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        $inner_cursor = $context->deserialize_required_property('o');
        $global_position = $context->get_optional_property('gp', 0);
        return new self($inner_cursor, $global_position);
    }
}
