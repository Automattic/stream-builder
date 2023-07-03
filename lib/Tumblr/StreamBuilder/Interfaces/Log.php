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

namespace Tumblr\StreamBuilder\Interfaces;

/**
 * Logging interface to avoid coupling to a specific logging library.
 */
interface Log
{
    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message The log message
     *
     * @return void
     */
    public function warning(string $message);

    /**
     * To log errors/exceptions.
     * @param \Throwable $e The error exception.
     * @param string|null $context The context this error happens.
     * @param array|null $extra The extra details for logging
     * @return void
     */
    public function exception(\Throwable $e, ?string $context = null, ?array $extra = null);

    /**
     * When you want to collect arbitrary non-error/warning logging information
     *
     * @param string $category The go log category
     * @param array $data The data to be written
     * @return mixed
     */
    public function debug(string $category, array $data);

    /**
     * A tick for collecting time-series metrics as a rate of events.
     * These are intended to track things like miss/hit
     * rates.
     *
     * An example:
     *    rate_tick('stream_cache', 'hit')
     *    rate_tick('stream_cache', 'miss')
     * It only tracks one axis/tag of an event. If you want to track multiple tags, use {@link superRateTick}.
     *
     * @param string $metric The metric name to associate with the event
     * @param string $operation The operation name
     * @param float $sample_rate The sample rate for recording the metric (0.0 = never, 0.5 = 50%, 1.0 = always)
     * @return void
     */
    public function rateTick(string $metric, string $operation, float $sample_rate = 1.0);

    /**
     * A tick for collecting time-series metrics.
     * Super tick allows you to define multiple tags, whereas {@link rateTick} is meant for very simple metrics-gathering.
     *
     * @param string $metric The metric name to associate with the event
     * @param array $tags Array of tags
     * @param float $sample_rate The sample rate for recording the metric (0=never, 1=always)
     * @return void
     */
    public function superRateTick(string $metric, array $tags, float $sample_rate = 1.0);

    /**
     * Store a value through time whereas {@link rateTick} is meant for storing rate.
     *
     * The metrics should be tagged by operation. So `{metric}_hits` will be a running
     * log of all hits against a service, for any operation.
     *
     * @param string $metric The metric name to associate with the event
     * @param string $operation The operation name
     * @param float $seconds The duration to aggregate
     * @param float $sample_rate The sample rate for recording the metric (0=never, 1=always)
     * @return void
     */
    public function histogramTick(string $metric, string $operation, float $seconds, float $sample_rate = 1.0);
}
