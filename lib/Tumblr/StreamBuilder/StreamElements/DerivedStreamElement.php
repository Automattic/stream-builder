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

namespace Tumblr\StreamBuilder\StreamElements;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;

/**
 * A derived stream element represents a derivation of a "leaf" (AKA "original") stream element.
 *
 * Whereas a "leaf" stream element is directly produced by some concrete source stream
 * implementation, elements will pass through multiple streams (filters, mixers, etc.) during
 * propagation through the object graph. Therefore, in order for those intermediate streams to not
 * expose implementation details, they will need to derive the elements that pass through them.
 *
 * Derivations are always going to override the $provider, but may also rewrite the $cursor. You
 * can get the leaf (underived) element by calling get_original_element.
 */
class DerivedStreamElement extends StreamElement
{
    /**
     * @var StreamElement
     */
    private $original_element;

    /**
     * Parent element is the adjacent father node of the derive tree.
     * @var StreamElement
     */
    private $parent_element;

    /**
     * Derive a StreamElement from another StreamElement, overriding the provider and cursor.
     *
     * @param StreamElement $parent_element The upstream element.
     * @param string $provider_identity The identity of the stream providing this element.
     * @param StreamCursor|null $cursor The new cursor value.
     */
    public function __construct(StreamElement $parent_element, string $provider_identity, ?StreamCursor $cursor = null)
    {
        parent::__construct($provider_identity, $cursor);
        $this->original_element = $parent_element->get_original_element();
        $this->parent_element = $parent_element;
        if (!empty($this->original_element->getComponent())) {
            $this->setComponent($this->original_element->getComponent());
        }
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function get_original_element(): StreamElement
    {
        return $this->original_element;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function get_cache_key()
    {
        // any derived element is cached as it's original
        // (i.e. derivations are filtered as if they are their originals).
        return $this->original_element->get_cache_key();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function get_parent_element(): StreamElement
    {
        return $this->parent_element;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function get_element_id(): string
    {
        return $this->original_element->get_element_id();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function to_string(): string
    {
        // any derived element is stringified as it's original.
        return $this->original_element->to_string();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function add_debug_info(string $header, string $field, $value)
    {
        $this->original_element->add_debug_info($header, $field, $value);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function get_debug_info(): array
    {
        return $this->original_element->get_debug_info();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['parent'] = $this->parent_element->to_template();
        return $base;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        return new self(
            $context->deserialize_required_property('parent'),
            $context->get_required_property('provider_id'),
            $context->deserialize_optional_property('cursor')
        );
    }
}
