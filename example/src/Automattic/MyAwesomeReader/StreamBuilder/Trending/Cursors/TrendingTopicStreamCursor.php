<?php declare(strict_types=1);

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

namespace Automattic\MyAwesomeReader\StreamBuilder\Trending\Cursors;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use function sprintf;

/**
 * A cursor for trending topics.
 */
class TrendingTopicStreamCursor extends StreamCursor
{
    /** @var int The offset in this cursor */
    private int $offset;

    /**
     * TrendingTopicsStreamCursor constructor.
     * @param int $offset Offset
     * @throws \InvalidArgumentException When offset is negative.
     */
    public function __construct(int $offset)
    {
        parent::__construct(Helpers::get_unqualified_class_name($this));
        if ($offset < 0) {
            throw new \InvalidArgumentException("Offset should not be negative");
        }
        $this->offset = $offset;
    }

    /**
     * @return int The offset.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    // phpcs:ignore
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return $other instanceof TrendingTopicStreamCursor;
    }

    // phpcs:ignore
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var TrendingTopicStreamCursor $other */
        return $this->getOffset() > $other->getOffset() ? $this : $other;
    }

    // phpcs:ignore
    protected function to_string(): string
    {
        return sprintf('%s(%d)', Helpers::get_unqualified_class_name($this), $this->getOffset());
    }

    // phpcs:ignore
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['offset'] = $this->getOffset();
        return $base;
    }

    // phpcs:ignore
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_required_property('offset'));
    }
}
