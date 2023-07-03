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

namespace Test\Tumblr\StreamBuilder\StreamCursors;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;

/**
 * Reverse chronological cursor
 */
final class TestingChronoCursor extends StreamCursor
{
    /** @var int */
    private $timestamp_ms;

    /**
     * @param int $timestamp_ms The timestamp
     */
    public function __construct(int $timestamp_ms)
    {
        $this->timestamp_ms = $timestamp_ms;
    }

    /**
     * @return int
     */
    public function get_timestamp_ms()
    {
        return $this->timestamp_ms;
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return ($other instanceof TestingChronoCursor);
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var TestingChronoCursor $other */
        return new self(min($this->timestamp_ms, $other->timestamp_ms));
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('TestingChronoCursor(%d)', $this->timestamp_ms);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'ts' => $this->timestamp_ms,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_required_property('ts'));
    }
}
