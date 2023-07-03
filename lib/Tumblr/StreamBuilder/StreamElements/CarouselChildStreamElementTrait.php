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
 * Default implementation of CarouselChildStreamElement
 */
trait CarouselChildStreamElementTrait
{
    /** @var string */
    private $parent_element_id;

    /**
     * Set parent CarouselStreamElement id as parent_element_id for this child stream element
     * @param string $element_id Element of parent element
     * @return void
     */
    public function set_parent_element_id(string $element_id): void
    {
        $this->parent_element_id = $element_id;
    }


    /**
     * get parent CarouselStreamElement id as parent_element_id for this child stream element
     * @return string
     */
    public function get_parent_element_id(): string
    {
        return $this->parent_element_id;
    }
}
