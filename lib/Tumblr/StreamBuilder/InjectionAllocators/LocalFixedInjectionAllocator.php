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

use Tumblr\StreamBuilder\InjectionAllocatorResult;

/**
 * An allocator that claims slots you tell it to, locally (within a page). Repeats in every page.
 * For global fixed position allocating, @see GlobalFixedInjectionAllocator
 */
class LocalFixedInjectionAllocator extends FixedInjectionAllocator
{
    /**
     * @inheritDoc
     */
    #[\Override]
    public function allocate(int $page_size, ?array $state = null): InjectionAllocatorResult
    {
        $out = [];
        foreach ($this->positions as $p) {
            if ($p < $page_size) {
                $out[] = $p;
            }
        }
        return new InjectionAllocatorResult($out, $state);
    }
}
