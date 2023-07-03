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

namespace Tumblr\StreamBuilder\Streams;

use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\TemplateProvider;

/**
 * A wrapper stream that is built from an existing template.
 * It powers template reusability, avoiding copy-pasting the same stream template fragments on different yaml files.
 * It can include many combined streams as sources, and stream filters too.
 * i.e. having the following:
 *  _type: Tumblr\StreamBuilder\Streams\TemplateReferenceStream
 *  name: 'demo.search'
 *  ctx: 'dashboard'
 * Would be the same as copy-pasting the content of components/Dashboard/Templates/demo.search.yml
 * into another template.
 */
class TemplateReferenceStream extends WrapStream
{
    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $template_name = $context->get_required_property('name');
        // The list of valid $ctx contexts names is set in the DependencyBag() constructor.
        $ctx = $context->get_required_property('ctx');
        $stream = StreamSerializer::from_template(new StreamContext(
            TemplateProvider::get_template($ctx, $template_name),
            $context->getMeta(),
            $context->get_cache_provider(),
            $context->get_current_identity()
        ));
        return new self($stream, $context->get_current_identity());
    }

    /**
     * @inheritDoc
     */
    protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        return $this->getInner()->enumerate($count, $cursor, $tracer, $option);
    }

    /**
     * @inheritDoc
     */
    protected function can_enumerate_with_time_range(): bool
    {
        return $this->getInner()->can_enumerate_with_time_range();
    }
}
