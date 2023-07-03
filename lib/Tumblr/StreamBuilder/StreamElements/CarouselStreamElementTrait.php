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

/***
 * Default implementation of CarouselStreamElement
 */
trait CarouselStreamElementTrait
{
    /**
     * get unique id for an stream element, this ensures only stream element can use this trait
     * @return string
     */
    abstract protected function get_element_id(): string;

    /**
     * This method must be implemented by the actual stream element class
     * @return StreamElement[]
     */
    abstract protected function get_contained_elements(): array;

    /**
     * set parent id for all carousel children, should be called in constructor
     * @return void
     */
    public function set_parent_element_id_for_contained_elements(): void
    {
        $contained_elements = $this->get_contained_elements();
        foreach ($contained_elements as $element) {
            if ($element instanceof CarouselChildStreamElement) {
                $element->set_parent_element_id($this->get_element_id());
            }
        }
    }
}
