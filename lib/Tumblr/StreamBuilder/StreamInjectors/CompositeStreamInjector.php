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

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;

/**
 * A stream injector which composed by an array of injectors.
 */
abstract class CompositeStreamInjector extends StreamInjector
{
    /** @var StreamInjector[] */
    private $injectors;

    /**
     * @param StreamInjector[] $injectors The injector array in priority order. Earlier injectors get precedence.
     * @param string $identity The string identifies this injector.
     * @throws TypeMismatchException If some argument is not a StreamInjector
     */
    public function __construct(array $injectors, string $identity)
    {
        parent::__construct($identity);
        foreach ($injectors as $i) {
            if (!($i instanceof StreamInjector)) {
                throw new TypeMismatchException(StreamInjector::class, $i);
            }
        }
        $this->injectors = $injectors;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['stream_injector_array'] = array_map(function ($inj) {
            /** @var StreamInjector $inj */
            return $inj->to_template();
        }, $this->injectors);
        return $base;
    }

    /**
     * @return StreamInjector[]
     */
    public function getInjectors(): array
    {
        return $this->injectors;
    }
}
