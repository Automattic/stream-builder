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

namespace Tests\Mock\Tumblr\StreamBuilder\Interfaces;

/**
 * A mocked implementation of the StreamBuilder log interface.
 */
class MockedLog implements \Tumblr\StreamBuilder\Interfaces\Log
{
    /**
     * @inheritDoc
     */
    public function warning(string $message)
    {
    }

    /**
     * @inheritDoc
     */
    public function exception(\Throwable $e, ?string $context = null, ?array $extra = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function debug(string $category, array $data)
    {
    }

    /**
     * @inheritDoc
     */
    public function rateTick(string $metric, string $operation, float $sample_rate = 1.0)
    {
    }

    /**
     * @inheritDoc
     */
    public function superRateTick(string $metric, array $tags, float $sample_rate = 1.0)
    {
    }

    /**
     * @inheritDoc
     */
    public function histogramTick(string $metric, string $operation, float $seconds, float $sample_rate = 1.0)
    {
    }
}
