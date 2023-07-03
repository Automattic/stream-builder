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

namespace Tumblr\StreamBuilder\FencepostRanking;

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\Interfaces\PostStreamElementInterface;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\Templatable;

/**
 * A fencepost represents a persisted view of a partially-ranked/saved stream segment.
 * Each fencepost has three components:
 *  1. The ranked part (the "head"), never empty.
 *  2. The not-yet-fetched part (represented as a cursor, the "tail").
 *  3. The id (timestamp) of the "next" fencepost, which may be null if this is
 *     known to be the final fencepost.
 * A fencepost could also be an injected fence, when $$is_inject_fence is true,
 * The head part saves the injected part, while the tail_cursor is the injected stream cursor.
 * Still confused? See more in @link docs/Fencepost.md
 */
final class Fencepost extends Templatable
{
    /** @var StreamElement[] */
    private array $head;
    /** @var StreamCursor|null */
    private ?StreamCursor $tail_cursor;
    /** @var int|null */
    private ?int $next_timestamp_ms;
    /** @var bool */
    private bool $is_inject_fence;

    /**
     * @param StreamElement[] $head The ranked part - an ordered array.
     * @param StreamCursor|null $tail_cursor The unretrieved part, as a cursor usable for enumeration.
     * @param int|null $next_timestamp_ms The timestamp of the next fencepost, if one exists.
     * @param bool $is_inject If the fencepost is a inject fencepost
     * @throws \InvalidArgumentException If head is empty.
     * @throws TypeMismatchException If $head array contains something that is not instanceof StreamElement.
     */
    public function __construct(
        array $head,
        ?StreamCursor $tail_cursor,
        ?int $next_timestamp_ms = null,
        bool $is_inject = false
    ) {
        if (empty($head)) {
            throw new \InvalidArgumentException('Head cannot be empty');
        }
        if (!$is_inject && is_null($tail_cursor)) {
            throw new \InvalidArgumentException('Non-inject Fencepost must have tail cursor');
        }
        foreach ($head as $e) {
            if (!($e instanceof StreamElement)) {
                throw new TypeMismatchException(StreamElement::class, $e);
            }
        }
        $this->head = $head;
        $this->tail_cursor = $tail_cursor;
        $this->next_timestamp_ms = $next_timestamp_ms;
        $this->is_inject_fence = $is_inject;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = [
            '_type' => get_class($this),
            'head' => array_map(function (StreamElement $se) {
                return $se->to_template();
            }, $this->head),
        ];
        if (!is_null($this->tail_cursor)) {
            $base['tail_cursor'] = $this->tail_cursor->to_template();
        }
        if (!is_null($this->next_timestamp_ms)) {
            $base['next_timestamp_ms'] = $this->next_timestamp_ms;
        }
        if ($this->is_inject_fence) {
            $base['is_inject'] = $this->is_inject_fence;
        }
        return $base;
    }

    /**
     * Return the string version of this fencepost
     * @return string
     */
    public function to_string(): string
    {
        $post_ids = array_map(function (StreamElement $elem) {
            $original_elem = $elem->get_original_element();
            return $original_elem instanceof PostStreamElementInterface ? $original_elem->getPostId() : $original_elem->get_element_id();
        }, $this->get_head());
        sort($post_ids);
        $posts = implode(';', $post_ids);
        $tail_cursor_str = $this->tail_cursor instanceof StreamCursor ? strval($this->tail_cursor) : '';
        return sprintf("%s_%d%s", $posts, (int) $this->is_inject_fence, $tail_cursor_str);
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->deserialize_array_property('head'),
            $context->deserialize_optional_property('tail_cursor'),
            $context->get_optional_property('next_timestamp_ms'),
            $context->get_optional_property('is_inject', false)
        );
    }

    /**
     * @return StreamElement[]
     */
    public function get_head(): array
    {
        return $this->head;
    }

    /**
     * @return StreamCursor|null
     */
    public function get_tail_cursor(): ?StreamCursor
    {
        return $this->tail_cursor;
    }

    /**
     * @return int|null
     */
    public function get_next_timestamp_ms()
    {
        return $this->next_timestamp_ms;
    }

    /**
     * @return bool
     */
    public function is_inject_fence(): bool
    {
        return $this->is_inject_fence;
    }
}
