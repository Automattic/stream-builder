<?php declare(strict_types=1);

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

namespace Test\Tumblr\StreamBuilder\StreamElements;

use Test\Tumblr\StreamBuilder\StreamCursors\MockMaxCursor;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use function sprintf;

/**
 * A simple StreamElement for testing deserialization, using a cursor that has max() as a combine function.
 */
final class MockMaxStreamElement extends LeafStreamElement
{
    /** @var int */
    private $value;

    /**
     * @param int $value The value of this element.
     * @param string $provider_identity The provider of this element.
     * @param MockMaxCursor $cursor The cursor of this element.
     * @param string|null $element_id An unique id used to trace the entire lifecycle of this element.
     */
    public function __construct(
        int $value,
        string $provider_identity,
        MockMaxCursor $cursor,
        ?string $element_id = null
    ) {
        parent::__construct($provider_identity, $cursor, $element_id);
        $this->value = $value;
    }

    /**
     * @inheritDoc
     */
    public function get_cache_key()
    {
        return $this->get_cache_key();
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return sprintf('TEST_MockMaxElement(%d)', $this->value);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['value'] = $this->value;
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_required_property('value'),
            $context->get_required_property('provider_id'),
            $context->deserialize_required_property('cursor'),
            $context->get_optional_property('element_id')
        );
    }
}
