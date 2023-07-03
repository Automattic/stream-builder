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
 * Class MaxIntCursor
 */
class MaxIntCursor extends StreamCursor
{
    /**
     * @var int
     */
    private $value;

    /**
     * MaxIntCursor constructor.
     * @param int $value Int value
     */
    public function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * @return int
     */
    public function get_value(): int
    {
        return $this->value;
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return $other instanceof self;
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var MaxIntCursor $other */
        return $other->value > $this->value ? $other : $this;
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf("MaxInt(%d)", $this->value);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['v'] = $this->value;
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_required_property('v'));
    }
}
