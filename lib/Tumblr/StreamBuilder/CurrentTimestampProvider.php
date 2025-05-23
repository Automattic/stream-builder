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
 * Provides the time, so we can mock it in unit tests to be deterministic. Very helpful!
 */
final class CurrentTimestampProvider extends TimestampProvider
{
    /**
     * @inheritDoc
     */
    #[\Override]
    public function time(): int
    {
        return time();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function time_ms(): int
    {
        return intval(1000.0 * microtime(true));
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context)
    {
        return new self();
    }
}
