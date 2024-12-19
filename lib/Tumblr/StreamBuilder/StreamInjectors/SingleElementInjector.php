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

namespace Tumblr\StreamBuilder\StreamInjectors;

use Tumblr\StreamBuilder\InjectionAllocators\InjectionAllocator;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamElementInjection;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Responsible for injecting a single element.
 */
abstract class SingleElementInjector extends StreamInjector
{
    /**
     * @var InjectionAllocator Allocator which controls which spots to inject.
     */
    protected $allocator;

    /**
     * @param InjectionAllocator $allocator The allocator to set injection positions.
     * @param string $identity The string identifies this injector.
     */
    public function __construct(InjectionAllocator $allocator, string $identity)
    {
        parent::__construct($identity);
        $this->allocator = $allocator;
    }

    /**
     * @inheritDoc
     */
    protected function _plan_injection(
        int $page_size,
        Stream $requesting_stream,
        ?array $state = null,
        ?StreamTracer $tracer = null
    ): InjectionPlan {
        $allocate_result = $this->allocator->allocate($page_size, $state);
        $slots = $allocate_result->get_allocate_output();
        $plan = [];
        foreach ($slots as $slot) {
            $stream_element = $this->get_inject_element();
            // add it to the plan
            $plan[$slot] = new StreamElementInjection($this, $stream_element);
        }
        return new InjectionPlan($plan, $allocate_result->get_injector_state());
    }

    /**
     * Get the single element to be injected
     * @return StreamElement
     */
    abstract protected function get_inject_element(): StreamElement;

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['stream_injection_allocator'] = $this->allocator->to_template();
        return $base;
    }
}
