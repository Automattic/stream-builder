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

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamSerializer;
use function is_null;
use function get_class;
use function sprintf;
use function implode;
use function count;

/**
 * A cursor which represents the conjunction of disparate states for disparate streams. Typically returned by a StreamMixer
 * that wants to maintain separate cursors for each source stream.
 */
final class MultiCursor extends StreamCursor
{
    /** @var StreamCursor[] Mapping from stream ids (strings) to StreamCursor objects */
    private $stream_to_cursor;
    /** @var array|null State of injector, if any */
    private $injector_state;

    /**
     * @param array $stream_to_cursor Mapping from stream ids (strings) to StreamCursor objects
     * @param array|null $injector_state State of injector, if any.
     */
    public function __construct(array $stream_to_cursor, array $injector_state = null)
    {
        $this->stream_to_cursor = $stream_to_cursor;
        $this->injector_state = $injector_state;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $stream_to_cursor_array = [];
        foreach ($this->stream_to_cursor as $s => $c) {
            if (!is_null($c)) {
                $stream_to_cursor_array[$s] = $c->to_template();
            }
        }
        return [
            '_type' => get_class($this),
            's' => $stream_to_cursor_array,
            'i' => $this->injector_state,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $stream_to_cursor_template = Helpers::idx2($template, 's', 'stream_to_cursor');
        $stream_to_cursor = [];
        foreach ($stream_to_cursor_template as $s => $c) {
            $stream_to_cursor[$s] = StreamSerializer::from_template($context->derive($c, sprintf('stream_to_cursor/%s', $s)));
        }
        return new self($stream_to_cursor, Helpers::idx2($template, 'i', 'injector_state'));
    }

    /**
     * Get the cursor for the given stream.
     * @param Stream $stream The stream for which to retrieve the cursor.
     * @return StreamCursor|null The cursor, otherwise null.
     */
    public function cursor_for_stream(Stream $stream)
    {
        return $this->stream_to_cursor[$stream->get_identity()] ?? null;
    }

    /**
     * Return a new MultiCursor which represents the current cursor AND the consumption of the provided element.
     * @param StreamElement $i The element to integrate.
     * @return StreamCursor The resultant cursor.
     */
    public function combine_from(StreamElement $i): StreamCursor
    {
        return $this->_combine_with(new MultiCursor([
            $i->get_provider_identity() => $i->get_cursor(),
        ]));
    }

    /**
     * @deprecated
     * @return array|null Get the injector state, if any
     */
    public function get_injector_state()
    {
        return $this->injector_state;
    }

    /**
     * Create an equivalent cursor using the provided injector state.
     * @param array|null $injector_state State of injector, if any.
     * @return MultiCursor
     */
    public function with_injector_state(array $injector_state = null): self
    {
        return new MultiCursor($this->stream_to_cursor, $injector_state);
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return ($other instanceof MultiCursor);
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var MultiCursor $other */
        $merged = $this->stream_to_cursor;
        foreach ($other->stream_to_cursor as $s => $c) {
            if (isset($merged[$s])) {
                /** @var StreamCursor $tmp */
                $tmp = $merged[$s];
                $merged[$s] = $tmp->combine_with($c);
            } else {
                $merged[$s] = $c;
            }
        }
        return new MultiCursor($merged, empty($this->injector_state) ? $other->injector_state : $this->injector_state);
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        $desc = [];
        foreach ($this->stream_to_cursor as $sid => $cur) {
            /** @var StreamCursor $cur */
            $desc[] = sprintf('%s:%s', $sid, $cur);
        }
        return sprintf('Multi(%s)', implode('; ', $desc));
    }

    /**
     * Check if a MultiCursor is empty.
     * @return bool
     */
    public function is_empty(): bool
    {
        return count($this->stream_to_cursor) == 0;
    }
}
