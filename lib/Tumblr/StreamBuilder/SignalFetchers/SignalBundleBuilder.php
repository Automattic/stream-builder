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

namespace Tumblr\StreamBuilder\SignalFetchers;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Mutable builder used for incrementally building an immutable SignalBundle
 */
final class SignalBundleBuilder
{
    /** @var mixed[][] */
    private $element_signals = [];

    /**
     * Add a signal to this builder.
     * @param StreamElement $element The element for which to add a signal.
     * @param string $signal_name The name of the signal being added.
     * @param mixed $signal_value The value of the signal being added.
     * @return self This, for method chaining.
     */
    public function with_signal_for_element(StreamElement $element, string $signal_name, $signal_value): self
    {
        $el_id = Helpers::memory_element_id($element);
        $this->element_signals[$el_id][$signal_name] = $signal_value;
        return $this;
    }

    /**
     * Build an immutable bundle from the signals contained in this builder.
     * @return SignalBundle
     */
    public function build(): SignalBundle
    {
        return new SignalBundle($this->element_signals);
    }
}
