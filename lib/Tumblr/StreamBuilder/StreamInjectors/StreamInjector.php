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

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\Templatable;

/**
 * Responsible for computing an injection plan for a given page of a given stream.
 */
abstract class StreamInjector extends Templatable
{
    /**
     * Generates an injection plan for a certain stream, based on previous injection state
     * @param int $page_size The overall page size to inject.
     * @param Stream $requesting_stream The stream requests an injection plan.
     * @param array|null $state The state of the injector.
     * @param StreamTracer|null $tracer The tracer traces the plan injection process.
     * @return InjectionPlan
     * @throws \Exception If injection planning fails for some injector.
     */
    final public function plan_injection(
        int $page_size,
        Stream $requesting_stream,
        array $state = null,
        StreamTracer $tracer = null
    ): InjectionPlan {
        $t0 = microtime(true);
        if (!$this->can_inject()) {
            return InjectionPlan::create_empty_plan();
        }

        $tracer && $tracer->begin_plan_injection($this, $page_size, $state);
        try {
            $result = $this->_plan_injection($page_size, $requesting_stream, $state, $tracer);
        } catch (\Exception $e) {
            $tracer && $tracer->fail_plan_injection($this, $page_size, [$t0, microtime(true) - $t0], $e);
            throw $e;
        }
        $tracer && $tracer->end_plan_injection(
            $this,
            $result->get_injection_count(),
            $result,
            [$t0, microtime(true) - $t0]
        );
        return $result;
    }

    /**
     * Indicate if you are eligible to be injected from this injector.
     * Override this if you have customized logic to determine can_inject.
     * @return bool Default to true.
     */
    protected function can_inject(): bool
    {
        return true;
    }

    /**
     * The real injection plan generation process.
     * @param int $page_size The overall page size to inject.
     * @param Stream $requesting_stream The stream requests an injection plan.
     * @param array|null $state The state of the injector.
     * @param StreamTracer|null $tracer The tracer traces the plan injection process.
     * @return InjectionPlan
     */
    abstract protected function _plan_injection(
        int $page_size,
        Stream $requesting_stream,
        array $state = null,
        StreamTracer $tracer = null
    ): InjectionPlan;

    /**
     * Get the string representation of the current injector.
     * @return string
     */
    final public function __toString(): string
    {
        return $this->to_string();
    }

    /**
     * Proxyed by __toString().
     * Default implementation is provided.
     * Override this if you want a more descriptive name.
     * @return string
     */
    protected function to_string(): string
    {
        return Helpers::get_unqualified_class_name($this);
    }
}
