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
use Tumblr\StreamBuilder\StreamContext;

/**
 * An injection allocator which injects uniformly, given a modulus and remainder.
 * It will starts with position $remainder.
 */
final class UniformInjectionAllocator extends InjectionAllocator
{
    /** @var int */
    private $modulus;
    /** @var int */
    private $remainder;

    /**
     * @param int $modulus The modulus, namely the distance between each injections.
     * @param int $remainder The remainder, the initial distance to start inject.
     * @throws \InvalidArgumentException If modulus is less than two.
     */
    public function __construct(int $modulus, int $remainder)
    {
        if ($modulus < 2) {
            throw new \InvalidArgumentException("Modulus must be at least 2");
        }
        $this->modulus = $modulus;
        $this->remainder = $remainder;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            "_type" => get_class($this),
            "modulus" => $this->modulus,
            "remainder" => $this->remainder,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        return new self($context->get_required_property('modulus'), $context->get_required_property('remainder'));
    }

    /**
     * @inheritDoc
     */
    public function allocate(int $page_size, ?array $state = null): InjectionAllocatorResult
    {
        $out = [];
        // If there's no next_offset is set, we use remainder, most likely in the first page.
        $offset = intval($state['next_offset'] ?? $this->remainder);

        for ($i = $offset; $i < $page_size; $i += $this->modulus) {
            $out[] = $i;
        }
        // next_offset is set to indicate the off positions in next page. Positions start from 0.
        if (empty($out)) {
            $state['next_offset'] = $offset - $page_size; // If not injected for a page at all.
        } else {
            $state['next_offset'] = $this->modulus - ($page_size - end($out));
        }
        // Reset internal pointer.
        reset($out);
        return new InjectionAllocatorResult($out, $state);
    }
}
