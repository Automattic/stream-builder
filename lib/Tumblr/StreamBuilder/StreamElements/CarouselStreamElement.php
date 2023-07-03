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

namespace Tumblr\StreamBuilder\StreamElements;

/**
 * For any stream element that is carousel style, meaning it contains other stream elements in it, and we want to
 * track events (click, impressions, etc.) for both the carousel element and its child element.
 */
interface CarouselStreamElement
{
    /**
     * @return StreamElement[]
     */
    public function get_contained_elements(): array;

    /**
     * set parent id for all carousel children, should be called in constructor
     * @return void
     */
    public function set_parent_element_id_for_contained_elements(): void;
}
