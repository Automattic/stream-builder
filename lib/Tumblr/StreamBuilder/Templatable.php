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
 * Ability to convert something into a template and recover a stream object from a template.
 *
 * A template looks like:
 *  array(
 *      "_type" => "Tumblr\\StreamBuilder\\Streams\\FilteredStream",
 *      "stream" => array(...),
 *      "stream_filter" => array(...),
 *      "retry_count": 2,
 *      "overfetch_ratio": 0.25,
 *  )
 */
abstract class Templatable extends Identifiable
{
    /** @var string|null The component this object belongs to */
    private $component;

    /** @var bool Is this component been skipped. */
    private $is_skipped_component = false;

    /**
     * @return bool Is this component been skipped.
     */
    public function isSkippedComponent(): bool
    {
        return $this->is_skipped_component;
    }

    /**
     * @param bool $isSkipped Mark this component skipped or not.
     * @return void
     */
    public function setSkippedComponent(bool $isSkipped): void
    {
        $this->is_skipped_component = $isSkipped;
    }

    /**
     * @return string|null
     */
    public function getComponent(): ?string
    {
        return $this->component;
    }

    /**
     * @param string|null $component The component.
     * @return void
     */
    public function setComponent(?string $component): void
    {
        $this->component = $component;
    }

    /**
     * Convert an object to a template.
     * @return array A serialized representative template for an object.
     */
    public function to_template(): array
    {
        $base = ['_type' => get_class($this)];
        if (!empty($this->getComponent())) {
            $base['_component'] = $this->getComponent();
        }
        return $base;
    }

    /**
     * Use this method to create a stream object from a template array.
     * @param StreamContext $context The context stores the stream template and other necessary data.
     * @return Templatable The templatable object corresponding to input.
     */
    abstract public static function from_template(StreamContext $context);
}
