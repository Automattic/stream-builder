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
 * A probabilistic (but non-independent) allocator which allocates along the expected value of the provided probability.
 *
 * For example, if we have a page size of 10 and a probability of 0.12 (12%), then the expected value of injections per page is 1.2.
 *
 * With a purely independent probabilistic allocator (e.g. ProbabilisticInjectionAllocator), by complete chance a given page could
 * easily end up with 0, 3, or even 5 injections.
 *
 * This allocator ensures that the injections per page matches the expected value. In the above example (12%) this class guarantees one
 * injection (1.0) and the remainder (0.2) is used for a weighted-coin toss (so 20% of the time, a page would have two injections, and
 * 80% of the time a page would hae one injection).
 *
 * Injection sites are chosen randomly, once their cardinality is known.
 */
final class ExpectedValueInjectionAllocator extends InjectionAllocator
{
    /** @var float */
    private $slot_injection_probability;

    /**
     * @param float $slot_injection_probability The probability to inject in a slot.
     */
    public function __construct(float $slot_injection_probability)
    {
        $this->slot_injection_probability = $slot_injection_probability;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
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
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        return new self($context->get_required_property('slot_injection_probability'));
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function allocate(int $page_size, ?array $state = null): InjectionAllocatorResult
    {
        $expected_value = $page_size * $this->slot_injection_probability;
        $count = floor($expected_value);
        if ((mt_rand() / mt_getrandmax()) < ($expected_value - $count)) {
            $count++;
        }
        $out = [];
        if ($count > 0) {
            $out = (array) array_rand(array_fill(0, $page_size, 1), (int) $count);
        }
        sort($out);
        return new InjectionAllocatorResult($out, $state);
    }
}
