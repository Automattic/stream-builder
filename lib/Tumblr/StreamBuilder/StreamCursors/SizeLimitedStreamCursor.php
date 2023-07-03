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

use Tumblr\StreamBuilder\StreamContext;

/**
 * Class SizeLimitedStreamCursor
 */
class SizeLimitedStreamCursor extends StreamCursor
{
    /**
     * Elements count that has been enumerated.
     * @var int
     */
    private $count;

    /**
     * Inner stream cursor, can be null if inner stream does not support pagination.
     * @var StreamCursor|null
     */
    private ?StreamCursor $cursor;

    /**
     * SizeLimitedStreamCursor constructor.
     * @param StreamCursor|null $cursor The inner stream cursor.
     * @param int $count Enumerated elements count.
     * @throws \InvalidArgumentException While count is negative.
     */
    public function __construct(?StreamCursor $cursor, int $count)
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('SizeLimitedStreamCursor expects a non-negative count input');
        }
        $this->cursor = $cursor;
        $this->count = $count;
    }

    /**
     * To get the current enumerated element count.
     * @return int
     */
    public function get_current_size(): int
    {
        return $this->count;
    }

    /**
     * To get the inner cursor.
     * @return null|StreamCursor
     */
    public function get_inner_cursor()
    {
        return $this->cursor;
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return ($other instanceof SizeLimitedStreamCursor);
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var SizeLimitedStreamCursor $other */
        return new self(
            StreamCursor::combine_all([$this->cursor, $other->cursor]),
            max($this->count, $other->count) + 1 // Magic! You should be able to prove it in math.
        );
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('SizeLimited(%s,%d)', $this->cursor, $this->count);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $output = [
            '_type' => get_class($this),
        ];

        if ($this->cursor instanceof StreamCursor) {
            $output['c'] = $this->cursor->to_template();
        }
        $output['ct'] = $this->count;
        return $output;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $cursor = $context->deserialize_optional_property('c');
        return new self($cursor, $context->get_required_property('ct'));
    }
}
