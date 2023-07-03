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
 * Interface Identifiable
 * An identifiable class should contain an identity which identifies the uniqueness of that instance.
 * NOTE: While an Identifiable class is initialized from a stream template, it usually comes with
 * some kind of path as its identity, for example 'template_name/stream_a/stream_b'
 */
abstract class Identifiable
{
    /**
     * @var string
     */
    private $identity;

    /**
     * Templatable constructor.
     * @param string $identity The identity indicates the uniqueness of this class,
     * $identity accepts any arbitrary string as long as it's sufficient for identification.
     * Examples: 'foo', 'filtered_legacy/stream/stream_wym', 'proportional/stream_weight_array/1/stream'
     * @throws \InvalidArgumentException When empty identity is passed in.
     */
    public function __construct(string $identity)
    {
        if (empty($identity)) {
            throw new \InvalidArgumentException(sprintf('%s identity should not be empty.', get_class($this)));
        }
        $this->identity = $identity;
    }

    /**
     * @param bool $class_name If you want to append class name at the end of an identity.
     * Identity becomes 'template_name/stream_a/stream_b[StreamB]'
     * @return string The identity.
     */
    final public function get_identity(bool $class_name = false): string
    {
        if ($class_name) {
            $short_class = Helpers::get_unqualified_class_name($this);
            return sprintf('%s[%s]', $this->identity, $short_class);
        }
        return $this->identity;
    }
}
