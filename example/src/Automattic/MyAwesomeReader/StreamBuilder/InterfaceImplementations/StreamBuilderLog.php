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

namespace Automattic\MyAwesomeReader\StreamBuilder\InterfaceImplementations;

use Tumblr\StreamBuilder\Interfaces\Log;

/**
 * A simple implementation of Log.
 */
class StreamBuilderLog implements Log
{
    /**
     * @inheritDoc
     */
    public function warning(string $message)
    {
        var_dump($message);
    }

    /**
     * @inheritDoc
     */
    public function exception(\Throwable $e, ?string $context = null, ?array $extra = null)
    {
        var_dump($e->getMessage());
    }

    /**
     * @inheritDoc
     */
    public function debug(string $category, array $data)
    {
        var_dump($data);
    }

    /**
     * @inheritDoc
     */
    public function rateTick(string $metric, string $operation, float $sample_rate = 1.0)
    {
        // TODO: Implement rateTick() method.
    }

    /**
     * @inheritDoc
     */
    public function superRateTick(string $metric, array $tags, float $sample_rate = 1.0)
    {
        // TODO: Implement superRateTick() method.
    }

    /**
     * @inheritDoc
     */
    public function histogramTick(string $metric, string $operation, float $seconds, float $sample_rate = 1.0)
    {
        // TODO: Implement histogramTick() method.
    }
}
