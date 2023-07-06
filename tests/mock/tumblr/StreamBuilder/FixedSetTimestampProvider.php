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
use function get_class;
use function floor;
use function array_shift;

/**
 * Class FixedSetTimestampProvider
 */
class FixedSetTimestampProvider extends TimestampProvider
{
    /**
     * The array of timestamps in ms.
     * @var array
     */
    private $timestamps_ms;

    /**
     * FixedTimestampProvider constructor.
     * @param array $timestamps_ms An array of timestamps in ms.
     */
    public function __construct(array $timestamps_ms)
    {
        $this->timestamps_ms = $timestamps_ms;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'timestamps_ms' => $this->timestamps_ms,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_required_property('timestamps_ms'));
    }

    /**
     * @inheritDoc
     */
    public function time(): int
    {
        if (empty($this->timestamps_ms)) {
            throw new \LogicException('Out of timestamps!');
        }
        return (int) floor(array_shift($this->timestamps_ms) / 1000);
    }

    /**
     * @inheritDoc
     */
    public function time_ms(): int
    {
        if (empty($this->timestamps_ms)) {
            throw new \LogicException('Out of timestamps!');
        }
        return array_shift($this->timestamps_ms);
    }
}
