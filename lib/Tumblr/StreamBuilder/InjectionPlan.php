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
 * An injection plan represents a mapping from 0-indexed slots to the injections
 * that will occupy those slots when the plan is applied.
 */
class InjectionPlan
{
    /**
     * @var StreamInjection[]
     */
    private $index_to_injection;

    /**
     * @var array|null An array represents the injection context between different pages.
     * A simple example could be array('next_offset' => 1) indicates, for next page,
     * the injector should start with an offset 1
     */
    private $injector_state;

    /**
     * @param array $index_to_injection An array mapping from sparse,
     * 0-based indexes to StreamInjection objects that will be injected at those positions.
     * @param array|null $injector_state An array representing the state of the injector, if any, after injection.
     * @throws TypeMismatchException If some value in the provided array is not a StreamInjection.
     */
    public function __construct(array $index_to_injection, ?array $injector_state = null)
    {
        foreach ($index_to_injection as $j) {
            if (!($j instanceof StreamInjection)) {
                throw new TypeMismatchException(StreamInjection::class, $j);
            }
        }
        $this->index_to_injection = $index_to_injection;
        $this->injector_state = $injector_state;
    }

    /**
     * @return int
     */
    public function get_injection_count(): int
    {
        return count($this->index_to_injection);
    }

    /**
     * @return StreamInjection[]
     */
    public function get_injections(): array
    {
        return $this->index_to_injection;
    }

    /**
     * @return array|null
     */
    public function get_injector_state()
    {
        return $this->injector_state;
    }

    /**
     * @param StreamResult $stream_result The result into which elements will be injected.
     * @return StreamResult The result of performing the injection.
     */
    public function apply(StreamResult $stream_result): StreamResult
    {
        $elems = $stream_result->get_elements();
        $injection_positions = array_keys($this->index_to_injection);
        sort($injection_positions); // injections need to happen in order!
        foreach ($injection_positions as $i) {
            /** @var StreamInjection $injection */
            $injection = $this->index_to_injection[$i];
            $el = $injection->execute($i, $elems);
            if (!is_null($el)) {
                array_splice($elems, $i, 0, [$el]);
            }
            // TODO: If we failed to execute, log injection failure.
        }
        return new StreamResult($stream_result->is_exhaustive(), $elems);
    }

    /**
     * To create an empty injection plan.
     * @return InjectionPlan
     */
    public static function create_empty_plan(): self
    {
        return new self([]);
    }

    /**
     * Get the string representation of the current InjectionPlan.
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(index_to_injection:%s  injector_state:%s)',
            Helpers::get_unqualified_class_name($this),
            implode(',', $this->index_to_injection),
            Helpers::json_encode($this->injector_state)
        );
    }
}
