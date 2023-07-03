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

use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * This composite injector is quite like PushdownCompositeInjector, combining an array of injector's plan by priority.
 * (Priority is higher if the injector appears before another inject in the injectors array.)
 * Unlike PushdownCompositeInjector, if injectors compete for one position, only highest priority injector wins.
 * Failed injector's injection are ignored.
 */
final class PrioritizedCompositeInjector extends CompositeStreamInjector
{
    /**
     * @inheritDoc
     */
    protected function _plan_injection(
        int $page_size,
        Stream $requesting_stream,
        array $state = null,
        StreamTracer $tracer = null
    ): InjectionPlan {
        if (is_null($state)) {
            $state = [];
        }
        $result = [];
        foreach ($this->getInjectors() as $injector) {
            /** @var StreamInjector $injector */
            $plan = $injector->plan_injection(
                $page_size,
                $requesting_stream,
                $state[$injector->get_identity()] ?? null,
                $tracer
            );

            $s = $plan->get_injector_state();
            if (empty($s)) {
                unset($state[$injector->get_identity()]);
            } else {
                $state[$injector->get_identity()] = $s;
            }

            foreach ($plan->get_injections() as $index => $injection) {
                if ($index < $page_size && !isset($result[$index])) {
                    $result[$index] = $injection;
                }
            }
            if (count($result) >= $page_size) {
                break;
            }
        }
        return new InjectionPlan($result, empty($state) ? null : $state);
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $injectors_template = $context->get_optional_property('stream_injector_array', []);
        $injectors = [];
        foreach ($injectors_template as $i => $i_template) {
            $injector = StreamSerializer::from_template(
                $context->derive($i_template, sprintf('stream_injector_array/%d', $i))
            );
            $injectors[] = $injector;
        }
        return new self($injectors, $context->get_current_identity());
    }
}
