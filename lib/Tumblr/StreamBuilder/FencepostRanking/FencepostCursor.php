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

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use function max;
use function is_null;
use function sprintf;
use function get_class;

/**
 * Cursor for a FencepostRankedStream
 */
final class FencepostCursor extends StreamCursor
{
    /*
     * HEY! These values are important!
     * "later" regions need to be numbered higher than "earlier" regions so we can use max() on them.
     * We left some space in between so that if you really need to add a region type in the middle
     * it is possible.
     */

    /** @var int */
    public const REGION_HEAD = 10;
    /** @var int */
    public const REGION_TAIL = 20;
    /** @var int */
    public const REGION_INJECT = 30;

    /** @var string[] */
    public const KNOWN_REGIONS = [
        self::REGION_HEAD => 'HEAD',
        self::REGION_TAIL => 'TAIL',
        self::REGION_INJECT => 'INJECT',
    ];

    /** @var int */
    private $fencepost_timestamp_ms;
    /** @var int */
    private $region;
    /** @var int */
    private $head_offset;
    /** @var StreamCursor|null */
    private $tail_cursor;
    /** @var StreamCursor|null */
    private $inject_cursor;

    /**
     * You can't call this, it's private. So use one of the helpers: ::create_head, ::create_tail, or ::create_final.
     * Those helpers do some argument sanity-checking about negative values described below...
     * @param int $fencepost_timestamp_ms The timestamp of the fencepost into which this cursor is valid.
     *     If and only if this is a "final" cursor, this should be -1 (see ::create_final)
     * @param int $region One of the REGION_* constants
     * @param int $head_offset The offset into the HEAD region, only meaningful when region == REGION_HEAD.
     *     If this is a tail or final cursor, should be -1. (see ::create_tail and ::create_final)
     * @param StreamCursor|null $tail_cursor The cursor into the tail. When healthy, it should only be used
     *     when region == REGION_TAIL however if a fencepost is missing or lost this cursor will be used to
     *     allow your dashboard to resume pagination.
     * @param StreamCursor|null $inject_cursor The cursor used for injection enumeration
     * @throws \InvalidArgumentException If the provided $region is invalid.
     */
    public function __construct(
        int $fencepost_timestamp_ms,
        int $region,
        int $head_offset,
        StreamCursor $tail_cursor = null,
        StreamCursor $inject_cursor = null
    ) {
        if ($region !== self::REGION_INJECT && is_null($tail_cursor)) {
            throw new \InvalidArgumentException('Head or Tail region must contains tail_cursor');
        }
        parent::__construct(Helpers::get_unqualified_class_name($this));
        $this->fencepost_timestamp_ms = $fencepost_timestamp_ms;
        $this->region = $region;
        $this->head_offset = $head_offset;
        $this->tail_cursor = $tail_cursor;
        $this->inject_cursor = $inject_cursor;
    }

    /**
     * @return int
     */
    public function get_fencepost_timestamp_ms(): int
    {
        return $this->fencepost_timestamp_ms;
    }

    /**
     * @return int
     */
    public function get_region(): int
    {
        return $this->region;
    }

    /**
     * @return int
     * @throws \LogicException If this is not a head-region cursor.
     */
    public function get_head_offset(): int
    {
        if ($this->region != self::REGION_HEAD) {
            throw new \LogicException('Request for head_offset on a non-head fencepost cursor');
        }
        return $this->head_offset;
    }

    /**
     * @return StreamCursor|null
     */
    public function get_tail_cursor(): ?StreamCursor
    {
        return $this->tail_cursor;
    }

    /**
     * @return StreamCursor|null
     */
    public function get_inject_cursor(): ?StreamCursor
    {
        return $this->inject_cursor;
    }

    /**
     * @inheritDoc
     */
    final protected function _can_combine_with(StreamCursor $other): bool
    {
        return ($other instanceof FencepostCursor);
    }

    /**
     * @inheritDoc
     */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        // prefer lesser fencepost timestamp:
        /** @var FencepostCursor $other */
        switch ($this->fencepost_timestamp_ms <=> $other->fencepost_timestamp_ms) {
            case 1:
                // $other is lesser
                return $other;
            case -1:
                // $this is lesser
                return $this;
            default:
                // identical fencepost timestamps:
                $new_region = max($this->region, $other->region);
                $new_head_offset = ($new_region == self::REGION_TAIL) ? -1 : max($this->head_offset, $other->head_offset);
                if ($new_region == self::REGION_INJECT) {
                    $new_inject_cursor = is_null($this->inject_cursor) ?
                        $other->inject_cursor : $this->inject_cursor->combine_with($other->inject_cursor);
                    $new_tail_cursor = is_null($this->tail_cursor) ? $other->tail_cursor : $this->tail_cursor->combine_with($other->tail_cursor);
                } else {
                    $new_inject_cursor = $this->inject_cursor ?? $other->inject_cursor;
                    $new_tail_cursor = $this->tail_cursor->combine_with($other->tail_cursor);
                }
                return new self($this->fencepost_timestamp_ms, $new_region, $new_head_offset, $new_tail_cursor, $new_inject_cursor);
        }
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        if ($this->inject_cursor) {
            $inject_str = $this->inject_cursor->to_string();
        } else {
            $inject_str = '';
        }
        return sprintf(
            'FencepostCursor(%d,%s,%d,%s%s)',
            $this->fencepost_timestamp_ms,
            self::KNOWN_REGIONS[$this->region],
            $this->head_offset,
            $this->tail_cursor->to_string(),
            $inject_str
        );
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = [
            '_type' => get_class($this),
            'f' => $this->fencepost_timestamp_ms,
            'r' => $this->region,
        ];
        if ($this->region == self::REGION_HEAD) {
            $base['h'] = $this->head_offset;
        }
        if ($this->tail_cursor) {
            $base['t'] = $this->tail_cursor->to_template();
        }
        if ($this->inject_cursor) {
            $base['i'] = $this->inject_cursor->to_template();
        }
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_required_property('f'),
            $context->get_required_property('r'),
            $context->get_optional_property('h', -1),
            $context->deserialize_optional_property('t'),
            $context->deserialize_optional_property('i')
        );
    }

    /**
     * Create a new cursor for the head region of the given fencepost.
     * @param int $fencepost_timestamp_ms The timestamp of the fencepost into which this cursor is valid.
     * @param int $head_offset The offset into the HEAD region.
     * @param StreamCursor $tail_cursor The cursor into the tail.
     * @throws \InvalidArgumentException If the head offset or timestamp is negative.
     * @return FencepostCursor
     */
    public static function create_head(int $fencepost_timestamp_ms, int $head_offset, StreamCursor $tail_cursor): self
    {
        if ($fencepost_timestamp_ms < 0) {
            throw new \InvalidArgumentException('Fencepost timestamp cannot be negative');
        }
        if ($head_offset < 0) {
            throw new \InvalidArgumentException('Head offset cannot be negative');
        }
        return new self($fencepost_timestamp_ms, self::REGION_HEAD, $head_offset, $tail_cursor);
    }

    /**
     * Create a new cursor for the tail region of the given fencepost.
     * @param int $fencepost_timestamp_ms The timestamp of the fencepost into which this cursor is valid.
     * @param StreamCursor $tail_cursor The cursor into the tail.
     * @throws \InvalidArgumentException If the timestamp is negative.
     * @return FencepostCursor
     */
    public static function create_tail(int $fencepost_timestamp_ms, StreamCursor $tail_cursor): self
    {
        if ($fencepost_timestamp_ms < 0) {
            throw new \InvalidArgumentException('Fencepost timestamp cannot be negative');
        }
        return new self($fencepost_timestamp_ms, self::REGION_TAIL, -1, $tail_cursor);
    }

    /**
     * Create a new cursor for the inject region of the given fencepost.
     * @param int $fencepost_timestamp_ms Timestamp of the fencepost
     * @param StreamCursor|null $tail_cursor The cursor into the tail.
     * @param StreamCursor|null $inject_cursor The cursor into the injection section
     * @throws \InvalidArgumentException If the timestamp is negative.
     * @return FencepostCursor
     */
    public static function create_inject(int $fencepost_timestamp_ms, ?StreamCursor $tail_cursor, ?StreamCursor $inject_cursor): self
    {
        if ($fencepost_timestamp_ms < 0) {
            throw new \InvalidArgumentException('Fencepost timestamp cannot be negative');
        }
        return new self($fencepost_timestamp_ms, self::REGION_INJECT, -1, $tail_cursor, $inject_cursor);
    }

    /**
     * Create a new cursor for the ultimate tail of a fencepost stream.
     * @param StreamCursor $tail_cursor The cursor into the tail.
     * @throws \InvalidArgumentException If the timestamp is negative.
     * @return FencepostCursor
     */
    public static function create_final(StreamCursor $tail_cursor): self
    {
        return new self(-1, self::REGION_TAIL, -1, $tail_cursor);
    }
}
