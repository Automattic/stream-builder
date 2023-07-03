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

namespace Test\Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\TimestampProvider;

/**
 * Class FixedTimestampProvider
 */
class FixedTimestampProvider extends TimestampProvider
{
    /**
     * The set current timestamp.
     * @var int
     */
    private $current_seconds;

    /**
     * FixedTimestampProvider constructor.
     * @param int $current_seconds Current timestamp in seconds.
     * @throws \InvalidArgumentException When current timestamp is not positive.
     */
    public function __construct(int $current_seconds)
    {
        if ($current_seconds <= 0) {
            throw new \InvalidArgumentException('Current timestamp needs to be positive.');
        }
        $this->current_seconds = $current_seconds;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'current_seconds' => $this->current_seconds,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_required_property('current_seconds'));
    }

    /**
     * @inheritDoc
     */
    public function time(): int
    {
        return $this->current_seconds;
    }

    /**
     * @inheritDoc
     */
    public function time_ms(): int
    {
        return $this->current_seconds * 1000;
    }
}
