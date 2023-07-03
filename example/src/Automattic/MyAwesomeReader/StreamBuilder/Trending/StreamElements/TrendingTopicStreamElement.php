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

namespace Automattic\MyAwesomeReader\StreamBuilder\Trending\StreamElements;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;

/**
 * Trending topic stream elements, one element represents one topic.
 */
class TrendingTopicStreamElement extends LeafStreamElement
{
    /**
     * @var string The topic.
     */
    private string $topic;

    /**
     * @param string $topic The topic id
     * @param string $provider_identity The identity
     * @param StreamCursor|null $cursor The cursor
     * @param string|null $element_id An unique id used to trace the entire lifecycle of this element.
     */
    public function __construct(string $topic, string $provider_identity, ?StreamCursor $cursor = null, ?string $element_id = null)
    {
        parent::__construct($provider_identity, $cursor, $element_id);
        $this->topic = $topic;
    }


    // phpcs:ignore
    public function get_cache_key()
    {
        return $this->topic;
    }

    // phpcs:ignore
    protected function to_string(): string
    {
        return "TrendingTopic:$this->topic";
    }

    // phpcs:ignore
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_required_property('topic'),
            $context->get_optional_property('provider_id', ''),
            $context->deserialize_optional_property('cursor'),
            $context->get_optional_property('element_id', null)
        );
    }

    // phpcs:ignore
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['topic'] = $this->topic;
        return $base;
    }
}
