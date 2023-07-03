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

use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;

/**
 * Basically a Future[StreamElement], returned as part of an InjectionPlan as a placeholder value for an item that will be injected.
 */
abstract class StreamInjection
{
    /** @var StreamInjector */
    protected $injector;

    /**
     * @param StreamInjector $injector The injector which created this injection.
     */
    public function __construct(StreamInjector $injector)
    {
        $this->injector = $injector;
    }

    /**
     * @return StreamInjector The injector responsible for this injection.
     */
    final public function get_injector(): StreamInjector
    {
        return $this->injector;
    }

    /**
     * To execute a real injection, convert the placeholder to a stream element.
     * @param int $position The position to inject.
     * @param StreamElement[] $elements The stream elements array, with possibly injected elements as well.
     * @return null|StreamElement
     */
    abstract public function execute(int $position, array $elements);

    /**
     * @return mixed A human-readable description of this injection, for logging purposes. Obviously, if the injection is asynchronous,
     * this might not know what is actually being injected (i.e. do NOT call execute inside the implementation).
     */
    abstract public function __toString(): string;
}
