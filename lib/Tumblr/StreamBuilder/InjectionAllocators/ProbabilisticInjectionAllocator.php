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
use function max;
use function min;
use function get_class;
use function mt_getrandmax;
use function mt_rand;

/**
 * A purely random allocator which, with some uniform probability, allocates a slot for injection.
 */
final class ProbabilisticInjectionAllocator extends InjectionAllocator
{
    /** @var float */
    private $slot_injection_probability;

    /**
     * @param float $slot_injection_probability The likelihood that a slot is occupied by an injection.
     */
    public function __construct(float $slot_injection_probability)
    {
        $this->slot_injection_probability = max(min($slot_injection_probability, 1.0), 0.0);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            "_type" => get_class($this),
            "slot_injection_probability" => $this->slot_injection_probability,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        return new self($context->get_required_property('slot_injection_probability'));
    }

    /**
     * @inheritDoc
     */
    public function allocate(int $page_size, array $state = null): InjectionAllocatorResult
    {
        $out = [];
        $mt_randmax = mt_getrandmax();
        for ($i = 0; $i < $page_size; $i++) {
            if ((mt_rand() / $mt_randmax) < $this->slot_injection_probability) {
                $out[] = $i;
            }
        }
        return new InjectionAllocatorResult($out, $state);
    }
}
