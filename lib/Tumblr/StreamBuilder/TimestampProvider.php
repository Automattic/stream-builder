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
 * Abstract Class TimestampProvider
 * Represents any class that can provide a timestamp.
 * This is a templatable component that can be set in stream templates, it controls streams that respect this
 * provider as the source of truth of the current timestamp. For example, a ShiftedTimestampProvider can control a time sensitive
 * stream to enumerate the future or the past.
 */
abstract class TimestampProvider extends Templatable
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(Helpers::get_unqualified_class_name($this));
    }

    /**
     * Get the current epoch timestamp in seconds.
     * @return int
     */
    abstract public function time(): int;

    /**
     * Get the current epoch timestamp in milliseconds.
     * @return int
     */
    abstract public function time_ms(): int;
}
