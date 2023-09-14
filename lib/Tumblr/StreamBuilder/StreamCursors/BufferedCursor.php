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

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * Cursor used by overfetching streams, to remember enumerated but not-yet-used elements.
 */
final class BufferedCursor extends StreamCursor
{
    /** @var StreamCursor|null The cursor used to refill the buffer from the inner stream. */
    private ?StreamCursor $inner_cursor;

    /** @var StreamElement[] Array of consumed but not returned elements. */
    private $buffer;

    /** @var CacheProvider */
    private $cache_provider;

    /**
     * @param StreamCursor|null $inner_cursor The cursor used to refill the buffer from the inner stream.
     * @param StreamElement[] $buffer Array of consumed but not returned elements.
     * @param CacheProvider $cache_provider The provider of cache.
     */
    public function __construct(?StreamCursor $inner_cursor, array $buffer, CacheProvider $cache_provider)
    {
        $this->inner_cursor = $inner_cursor;
        $this->buffer = $buffer;
        $this->cache_provider = $cache_provider;
    }

    /**
     * @return StreamElement[] The elements in the cursor's buffer
     */
    public function get_buffer(): array
    {
        return $this->buffer;
    }

    /**
     * @return StreamCursor|null
     */
    public function get_inner_cursor()
    {
        return $this->inner_cursor;
    }

    /**
     * @inheritDoc
     */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        if ($other instanceof BufferedCursor) {
            return (is_null($this->inner_cursor) || $this->inner_cursor->can_combine_with($other->inner_cursor));
        } else {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var BufferedCursor $other */
        $combined_inner = StreamCursor::combine_all([ $this->inner_cursor, $other->inner_cursor ]);
        /** @var StreamElement[] $combined_buffer Intersection of buffers */
        $combined_buffer = array_values(array_intersect_key(
            Helpers::element_identity_map($this->buffer),
            Helpers::element_identity_map($other->buffer)
        ));
        // NOTE: the caller's cache provider will be used in the resultant cursor.
        // but an entire enumeration proceeds using the CacheProvider originally presented
        // to the StreamSerializer, so these are basically always the same instance.
        return new self($combined_inner, $combined_buffer, $this->cache_provider);
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        $elem_strings = [];
        foreach ($this->buffer as $elem) {
            $elem_strings[] = ((string) $elem);
        }
        return sprintf('BufferedCursor(%s,{%s})', $this->inner_cursor, implode(',', $elem_strings));
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = [ '_type' => get_class($this) ];
        if (!is_null($this->inner_cursor)) {
            $base['ic'] = $this->inner_cursor->to_template();
        }
        if (!empty($this->buffer)) {
            $buffer_templates = [];
            foreach ($this->buffer as $elem) {
                $buffer_templates[] = $elem->to_template();
            }
            $json = Helpers::json_encode($buffer_templates);
            $buffer_key = sprintf('bufcur_%s', md5($json));
            // TODO: configure TTL here too?
            $this->cache_provider->set(CacheProvider::OBJECT_TYPE_BUFFERED_STREAM_ELEMENTS, $buffer_key, $json);
            $base['b'] = $buffer_key;
        }
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $cache = $context->get_cache_provider();
        /** @var StreamElement[] $buffer */
        $buffer = [];
        if ($buffer_key = $context->get_optional_property('b')) {
            if ($buffer_templates_json = $cache->get(CacheProvider::OBJECT_TYPE_BUFFERED_STREAM_ELEMENTS, $buffer_key)) {
                $buffer_templates = Helpers::json_decode($buffer_templates_json);
                foreach ($buffer_templates as $i => $buffer_template) {
                    $buffer[] = StreamSerializer::from_template($context->derive($buffer_template, sprintf('b/%d', $i)));
                }
            } else {
                StreamBuilder::getDependencyBag()->getLog()
                    ->rateTick('algodash_errors', 'buffered_cursor_key_not_found');
            }
        }
        return new self($context->deserialize_optional_property('ic'), $buffer, $cache);
    }
}
