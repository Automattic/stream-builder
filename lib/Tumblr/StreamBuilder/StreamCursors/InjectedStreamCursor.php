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

namespace Tumblr\StreamBuilder\StreamCursors;

use Tumblr\StreamBuilder\StreamContext;

/**
 * Cursor for InjectedStream
 */
final class InjectedStreamCursor extends StreamCursor
{
    /**
     * @var StreamCursor|null
     */
    private $inner_cursor;

    /**
     * @var array|null
     */
    private $injector_state;

    /**
     * InjectedStreamCursor constructor
     *
     * @param StreamCursor $inner_cursor    The cursor for the inner stream.
     * @param array|null $injector_state    The injector state.
     */
    public function __construct(StreamCursor $inner_cursor = null, array $injector_state = null)
    {
        $this->inner_cursor = $inner_cursor;
        $this->injector_state = $injector_state;
    }

    /**
     * To get inner cursor.
     *
     * @return StreamCursor|null
     */
    public function get_inner_cursor()
    {
        return $this->inner_cursor;
    }

    /**
     * To get injector state.
     *
     * @return array|null
     */
    public function get_injector_state()
    {
        return $this->injector_state;
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        if (!($other instanceof InjectedStreamCursor)) {
            return false;
        }
        if (is_null($this->inner_cursor)) {
            return true;
        }
        return $this->inner_cursor->can_combine_with($other->inner_cursor);
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var InjectedStreamCursor $other */
        return new self(
            StreamCursor::combine_all([$this->inner_cursor, $other->inner_cursor]),
            $this->injector_state ?: $other->injector_state
        );
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('InjectedStreamCursor(%s)', $this->inner_cursor);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'c' => is_null($this->inner_cursor) ? null : $this->inner_cursor->to_template(),
            'i' => $this->injector_state,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        return new self($context->deserialize_optional_property('c'), $context->get_optional_property('i'));
    }
}
