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

namespace Tumblr\StreamBuilder;

/**
 * Class ShiftedTimestampProvider
 */
final class ShiftedTimestampProvider extends TimestampProvider
{
    /**
     * Offset in seconds, if offset is smaller than 0, time will behind current,
     * if offset is greater than 0, time is set to the future.
     * @var int
     */
    private $offset_seconds;

    /**
     * ShiftedTimestampProvider constructor.
     * @param int $offset_seconds Offset in seconds.
     */
    public function __construct(int $offset_seconds)
    {
        $this->offset_seconds = $offset_seconds;
    }

    /**
     * @inheritDoc
     */
    public function time(): int
    {
        return time() + $this->offset_seconds;
    }

    /**
     * @inheritDoc
     */
    public function time_ms(): int
    {
        return intval(1000.0 * microtime(true)) + 1000 * $this->offset_seconds;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'offset_seconds' => $this->offset_seconds,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_optional_property('offset_seconds', 0)
        );
    }
}
