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

namespace Tumblr\StreamBuilder\StreamFilters;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A stream filter that does nothing. Can be useful for debugging.
 */
class NoopStreamFilter extends StreamFilter
{
    /**
     * @inheritDoc
     */
    #[\Override]
    final public function filter_inner(array $elements, ?StreamFilterState $state = null, ?StreamTracer $tracer = null): StreamFilterResult
    {
        $retained = $elements;
        $released = [];
        return StreamFilterResult::create_from_leaf_filter($retained, $released);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function get_cache_key()
    {
        return 'NoopStreamFilter';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_current_identity());
    }
}
