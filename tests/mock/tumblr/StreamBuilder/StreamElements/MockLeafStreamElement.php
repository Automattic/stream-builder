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
namespace Tests\mock\tumblr\StreamBuilder\StreamElements;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;

/**
 * A simple mock implementation of LeaStreamElement for testing.
 */
class MockLeafStreamElement extends LeafStreamElement
{

    /**
     * @inheritDoc
     */
    public function get_cache_key()
    {
        return 'cache_key';
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return 'mock_leaf_stream_element';
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new MockLeafStreamElement('some_identity');
    }
}
