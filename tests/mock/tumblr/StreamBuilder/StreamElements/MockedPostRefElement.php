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

namespace Test\Mock\Tumblr\StreamBuilder\StreamElements;

use Tumblr\StreamBuilder\Interfaces\PostStreamElementInterface;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\SearchStreamCursor;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;

/**
 * A mocked version of PostRefElement
 */
class MockedPostRefElement extends LeafStreamElement implements ChronologicalStreamElement, PostStreamElementInterface
{
    /**
     * @var int Post id
     */
    private int $post_id;

    /**
     * @var int Blog id
     */
    private int $blog_id;

    /**
     * @var int Timestamp in milliseconds
     */
    private int $timestamp_ms;

    /**
     * @param int $post_id Post id
     * @param int $blog_id Blog id
     * @param int $timestamp_ms Timestamp in milliseconds
     */
    public function __construct(int $post_id, int $blog_id, int $timestamp_ms = 0)
    {
        parent::__construct('whatever', new SearchStreamCursor($post_id));
        $this->post_id = $post_id;
        $this->blog_id = $blog_id;
        $this->timestamp_ms = $timestamp_ms;
    }

    /**
     * @return int Post id
     */
    public function get_post_id(): int
    {
        return $this->post_id;
    }

    /** @inheritDoc */
    public function get_cache_key(): ?string
    {
        return $this->to_string();
    }

    /** @inheritDoc */
    protected function to_string(): string
    {
        return sprintf('mock(%d)', 0);
    }

    /** @inheritDoc */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['post_id'] = $this->post_id;
        $base['blog_id'] = $this->blog_id;
        return $base;
    }

    /** @inheritDoc */
    public static function from_template(StreamContext $context): \Tumblr\StreamBuilder\Templatable
    {
        return new MockedPostRefElement(
            $context->get_required_property('post_id'),
            $context->get_required_property('blog_id')
        );
    }

    /**
     * @inheritDoc
     */
    public function get_timestamp_ms(): int
    {
        return $this->timestamp_ms;
    }

    /**
     * @inheritDoc
     */
    public function getBlogId(): string
    {
        return (string) $this->blog_id;
    }

    /**
     * @inheritDoc
     */
    public function getPostId(): string
    {
        return (string) $this->post_id;
    }

    /**
     * @inheritDoc
     */
    public function getPostKey(): array
    {
        return [
            'id' => $this->post_id,
            'tumblelog_id' => $this->getBlogId(),
        ];
    }
}
