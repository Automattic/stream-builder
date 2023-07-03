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

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Immutable bundle of signals.
 */
final class SignalBundle
{
    /** @var mixed[][] A 2D array of [element_id][signal_key], where both element_id and signal_key are strings.
     * - element_id is the value returned from SignalBundle::memory_element_id($element), for example 'ptr_000000006f2111b6000000003cb4dc65'.
     * - signal_key is any string, as decided within a fetcher, for the signal(s) it returns.
     */
    private $element_signals;

    /**
     * @param mixed[][] $element_signals The signals for each element as retrieved by the fetcher.
     * See the docblock for $element_signals to understand what is in here.
     */
    public function __construct(array $element_signals)
    {
        $this->element_signals = $element_signals;
    }

    /**
     * Get all the signals from this bundle for a given element.
     * @param StreamElement $element The element for which to retrieve signals.
     * @return mixed[]
     */
    public function get_signals_for_element(StreamElement $element): array
    {
        $el_id = Helpers::memory_element_id($element);
        return $this->element_signals[$el_id] ?? [];
    }

    /**
     * Get a single signal from this bundle for a given element.
     * @param StreamElement $element The element for which to retrieve signals.
     * @param string $signal_name The name of the signal to retrieve.
     * @return mixed|null
     */
    public function get_signal_for_element(StreamElement $element, string $signal_name)
    {
        $signals = $this->get_signals_for_element($element);
        return $signals[$signal_name] ?? null;
    }

    /**
     * @param SignalBundle[] $bundles The bundles to combine. Signals in later bundles will take precedence
     * over signals in earlier bundles if they share a key
     * @throws TypeMismatchException If some element of the $bundles array is not a SignalBundle.
     * @return SignalBundle The combined bundle.
     */
    public static function combine_all(array $bundles): SignalBundle
    {
        // this could almost be implemented using array_merge_recursive, except for its tendency to create
        // arrays as leaves rather than prioritize the last value :). So we do the outer (per-element)
        // iteration manually and then use vanilla array_merge to combine signals for each element.
        $flat_signals = [];
        foreach ($bundles as $bundle) {
            if (!($bundle instanceof SignalBundle)) {
                throw new TypeMismatchException(SignalBundle::class, $bundle);
            }
            foreach ($bundle->element_signals as $element_id => $element_signals) {
                /** @var mixed[] $element_signals */
                $flat_signals[$element_id] = array_merge($flat_signals[$element_id] ?? [], $element_signals);
            }
        }
        return new SignalBundle($flat_signals);
    }

    /**
     * Get string representation of current SignalBundle
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            Helpers::get_unqualified_class_name($this),
            Helpers::json_encode($this->element_signals)
        );
    }
}
