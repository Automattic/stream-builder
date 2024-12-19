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

namespace Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;

/**
 * A structure representing the result of a allocating operation, containing the allocated output array
 * and an injector state needed to pass along.
 */
final class InjectionAllocatorResult
{
    /** @var array */
    private $out;
    /** @var array */
    private $state;

    /**
     * @param array $out The allocator output, which is an array of injection positions.
     * @param array|null $state The injector state to coordinate between different pages.
     * @throws TypeMismatchException If position is not an int.
     */
    public function __construct(array $out, ?array $state = null)
    {
        foreach ($out as $position) {
            if (!is_int($position)) {
                throw new TypeMismatchException('int', $position);
            }
        }
        $this->out = $out;
        $this->state = $state;
    }

    /**
     * @return array The allocator output, which is an array of injection positions.
     */
    public function get_allocate_output(): array
    {
        return $this->out;
    }

    /**
     * @return array|null The injector state to coordinate between different pages.
     */
    public function get_injector_state()
    {
        return $this->state;
    }

    /**
     * @return int The number of positions that the allocator outputs.
     */
    public function get_allocate_output_count(): int
    {
        return count($this->out);
    }

    /**
     * Get the string representation of the current InjectionAllocatorResult.
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(out:%s  state:%s)',
            Helpers::get_unqualified_class_name($this),
            implode(',', $this->out),
            Helpers::json_encode($this->state)
        );
    }
}
