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
use Tumblr\StreamBuilder\Templatable;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Abstraction over a thing that fetches signals
 * @see SignalRankedStream
 */
abstract class SignalFetcher extends Templatable
{
    /**
     * Fetch signals for a set of elements.
     * @param StreamElement[] $stream_elements Elements for which to fetch signals.
     * @param StreamTracer|null $tracer Tracer to use for metrics and logging of signal fetching process.
     * @return SignalBundle
     * @throws TypeMismatchException If some element is not a StreamElement.
     * @throws \Exception If the signal fetcher fails for some other reason.
     */
    final public function fetch(array $stream_elements, ?StreamTracer $tracer = null): SignalBundle
    {
        foreach ($stream_elements as $stream_element) {
            if (!($stream_element instanceof StreamElement)) {
                throw new TypeMismatchException(StreamElement::class, $stream_element);
            }
        }
        $t0 = microtime(true);

        if ($tracer) {
            $tracer->begin_signal_fetch($this);
        }
        try {
            $bundle = $this->fetch_inner($stream_elements, $tracer);
        } catch (\Exception $e) {
            if ($tracer) {
                $tracer->fail_signal_fetch($this, [$t0, microtime(true) - $t0], $e);
            }
            throw $e;
        }
        if ($tracer) {
            $tracer->end_signal_fetch($this, [$t0, microtime(true) - $t0], $bundle);
        }
        return $bundle;
    }

    /**
     * Implement this method to fetch signals and load them into the builder.
     * @param StreamElement[] $stream_elements The elements for which to fetch signals.
     * @param StreamTracer|null $tracer Tracer to use for metrics and logging of signal fetching process.
     * @return SignalBundle
     */
    abstract protected function fetch_inner(array $stream_elements, ?StreamTracer $tracer = null): SignalBundle;

    /**
     * Get the string representation of the current signal fetcher.
     * @return string
     */
    final public function __toString(): string
    {
        return $this->to_string();
    }

    /**
     * Proxyed by __toString().
     * Default implementation is provided.
     * Override this if you want a more descriptive name.
     * @return string
     */
    protected function to_string(): string
    {
        return Helpers::get_unqualified_class_name($this);
    }
}
