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

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;

/**
 * Base class for StreamElements which originated from an ultimate source, i.e. are not derived,
 * and are therefore their own "parent".
 */
abstract class LeafStreamElement extends StreamElement
{
    /**
     * @var string[][]
     */
    private $debug_info;

    /**
     * An unique id for an stream element, will be used for tracing the entire lifecycle of this element,
     * including being generated, ranked, filtered, converted.
     * @var string
     */
    private $element_id;

    /**
     * LeafStreamElement constructor.
     * @param string $provider_identity The identity of the stream which created this element.
     * @param StreamCursor|null $cursor The cursor representing the pagination state after consuming this element.
     * @param string|null $element_id An unique id used to trace the entire lifecycle of this element.
     * NOTE: if element_id is not presented in the constructor call, it will regenerate a new uuid, you would lose
     * track of the previous element_id if it had one before.
     */
    public function __construct(string $provider_identity, ?StreamCursor $cursor = null, ?string $element_id = null)
    {
        if ($element_id === null) {
            $this->element_id = Helpers::get_uuid(); // this is unique per host, process and microsecond.
        } else {
            $this->element_id = $element_id;
        }

        parent::__construct($provider_identity, $cursor);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function get_original_element(): StreamElement
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function get_parent_element(): StreamElement
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function get_element_id(): string
    {
        return $this->element_id;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function add_debug_info(string $header, string $field, $value)
    {
        if (!isset($this->debug_info[$header])) {
            $this->debug_info[$header] = [];
        }
        $this->debug_info[$header][$field] = $value;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final public function get_debug_info(): array
    {
        return $this->debug_info ?? [];
    }
}
