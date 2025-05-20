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

namespace Tumblr\StreamBuilder\InjectionAllocators;

use Tumblr\StreamBuilder\StreamContext;

/**
 * An allocator which just uses the slots you tell it to. Slots outside the page boundary are ignored.
 */
abstract class FixedInjectionAllocator extends InjectionAllocator
{
    /**
     * Array of positions to inject at.
     * @var array
     */
    protected $positions;

    /**
     * FixedInjectionAllocator constructor.
     * @param array $positions The 0-based integer position(s) at which to inject.
     * @throws \InvalidArgumentException When positions array contains non-int element.
     */
    public function __construct(array $positions)
    {
        foreach ($positions as $pos) {
            if (!is_int($pos)) {
                throw new \InvalidArgumentException('Position should be an integer.');
            }
        }
        sort($positions);
        $this->positions = array_unique($positions);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'positions' => $this->positions,
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        return new static($context->get_optional_property('positions', []));
    }
}
