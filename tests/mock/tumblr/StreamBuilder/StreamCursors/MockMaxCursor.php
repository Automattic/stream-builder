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
 * A cursor for testing that uses max() as a combiner.
 */
final class MockMaxCursor extends StreamCursor
{
    /** @var int */
    private $max;

    /**
     * @param int $max The maximum value of the elements.
     */
    public function __construct(int $max)
    {
        $this->max = $max;
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return ($other instanceof MockMaxCursor);
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var MockMaxCursor $other */
        return new self(max($this->max, $other->max));
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('TEST_MockMaxCursor(%d)', $this->max);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'max' => $this->max,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_required_property('max')
        );
    }
}
