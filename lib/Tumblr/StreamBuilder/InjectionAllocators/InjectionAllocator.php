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
use Tumblr\StreamBuilder\Templatable;

/**
 * Injection Allocators are helper utilities that certain injectors may decide to use.
 * They provide pluggable strategies for how to allocate injections to slots on a page,
 * so that the injector can concern itself only with the actual injection, and not the slot distribution.
 * See implementations for concrete examples.
 */
abstract class InjectionAllocator extends Templatable
{
    /**
     * @param int $page_size The size of the page being injected into.
     * @param array|null $state The state of the injector.
     * @return InjectionAllocatorResult .
     */
    abstract public function allocate(int $page_size, ?array $state = null): InjectionAllocatorResult;
}
