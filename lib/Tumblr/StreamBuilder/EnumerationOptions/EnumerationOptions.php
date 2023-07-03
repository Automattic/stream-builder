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

namespace Tumblr\StreamBuilder\EnumerationOptions;

/**
 * The options we apply to optimize enumeration behavior, currently only supports time range options.
 */
class EnumerationOptions
{
    /**
     * @var int Minimum exlusive timestamp in milliseconds
     */
    private $min_ts_exclusive;

    /**
     * @var int Maximum inclusive timestamp in milliseconds
     */
    private $max_ts_inclusive;

    /**
     * EnumerationOptions constructor.
     * @param int $max_ts_inclusive Maxminum Timestamp inclusive for enumeration
     * @param int $min_ts_exclusive Minimum Timestamp exlusive for enumeration
     * @throws \InvalidArgumentException When given ts range is invalid
     */
    public function __construct(?int $max_ts_inclusive, ?int $min_ts_exclusive)
    {
        if (is_int($max_ts_inclusive) && is_int($min_ts_exclusive)) {
            if ($min_ts_exclusive >= $max_ts_inclusive) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Invalid time range, max ts %d should be greater than min ts %d',
                        $max_ts_inclusive,
                        $min_ts_exclusive
                    )
                );
            }
        }
        $this->min_ts_exclusive = $min_ts_exclusive;
        $this->max_ts_inclusive = $max_ts_inclusive;
    }

    /**
     * If should use time range enumeration
     * @return bool
     */
    public function has_time_range(): bool
    {
        return ($this->max_ts_inclusive || $this->min_ts_exclusive);
    }

    /**
     * Enumeration before ts getter
     * @return int|null
     */
    public function get_enumerate_before_ts()
    {
        return $this->max_ts_inclusive;
    }

    /**
     * Enumeration after ts getter
     * @return int|null
     */
    public function get_enumerate_after_ts()
    {
        return $this->min_ts_exclusive;
    }

    /**
     * @param int $timestamp_in_ms Timestamp in milliseconds
     * @return bool If given ts is valid for the option timerange
     */
    public function is_valid_ts(int $timestamp_in_ms): bool
    {
        if ($this->has_time_range()) {
            if (is_int($this->min_ts_exclusive) && $timestamp_in_ms <= $this->min_ts_exclusive) {
                return false;
            }
            if (is_int($this->max_ts_inclusive) && $timestamp_in_ms > $this->max_ts_inclusive) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the time range of the defined option
     *
     * @return int
     */
    public function get_time_range_in_seconds(): int
    {
        if (!$this->has_time_range()) {
            return false;
        }

        $start = $this->max_ts_inclusive ?? microtime(true) * 1000;
        $end = $this->min_ts_exclusive ?? 0;
        $time_range_in_seconds = ($start - $end) / 1000;
        return intval($time_range_in_seconds);
    }
}
