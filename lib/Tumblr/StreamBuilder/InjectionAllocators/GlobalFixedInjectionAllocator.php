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
 * Class GlobalFixedInjectionAllocator
 * An allocator claims slots you tell it to, globally. Once all slots are injected, it will skip.
 * For page specific fixed position allocating, @see LocalFixedInjectionAllocator
 */
class GlobalFixedInjectionAllocator extends FixedInjectionAllocator
{
    /**
     * @inheritDoc
     */
    public function allocate(int $page_size, array $state = null): InjectionAllocatorResult
    {
        $pos_base = $state['pos_base'] ?? 0;
        $out = [];
        foreach ($this->positions as $pos) {
            $page_pos = $pos - $pos_base;
            if ($page_pos >= 0 && $page_pos < $page_size) {
                $out[] = $page_pos;
            }
        }
        $state['pos_base'] = $pos_base + $page_size;
        return new InjectionAllocatorResult($out, $state);
    }
}
