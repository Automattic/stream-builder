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

namespace Tumblr\StreamBuilder\StreamTracers;

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\Identifiable;

/**
 * A StreamTracer which executes, in turn, a number of other StreamTracers.
 */
final class CompositeStreamTracer extends StreamTracer
{
    /** @var StreamTracer[] */
    private $children;

    /**
     * @param array $members Array of StreamTracer objects.
     * @throws TypeMismatchException If some element of the array is not a StreamTracer.
     */
    public function __construct(array $members)
    {
        foreach ($members as $st) {
            if (!($st instanceof StreamTracer)) {
                throw new TypeMismatchException(StreamTracer::class, $st);
            }
        }
        $this->children = $members;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function trace_event(
        string $event_category,
        Identifiable $sender,
        string $event_name,
        ?array $timing = [],
        ?array $meta = []
    ): void {
        foreach ($this->children as $c) {
            $c->trace_event($event_category, $sender, $event_name, $timing, $meta);
        }
    }
}
