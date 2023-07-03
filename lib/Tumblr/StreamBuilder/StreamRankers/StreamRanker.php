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

namespace Tumblr\StreamBuilder\StreamRankers;

use Tumblr\StreamBuilder\Exceptions\IllegalRankerException;
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\Templatable;

/**
 * A ranker for stream elements.
 */
abstract class StreamRanker extends Templatable
{
    /**
     * Rank a set of elements, returning the same elements in a different order.
     * @param StreamElement[] $stream_elements The elements to be ranked.
     * @param StreamTracer|null $tracer The tracer used during enumeration.
     * @return StreamElement[] The ranked elements, guaranteed to be the same elements as input but possibly reordered.
     * @throws TypeMismatchException If some element is not a StreamElement.
     * @throws IllegalRankerException If the rank method adds or removes elements.
     * Also, rethrows \Exception if some other error occurs, but PHPCBF does not want me to document this.
     */
    final public function rank(array $stream_elements, StreamTracer $tracer = null): array
    {
        foreach ($stream_elements as $stream_element) {
            if (!($stream_element instanceof StreamElement)) {
                throw new TypeMismatchException(StreamElement::class, $stream_element);
            }
        }

        $t0 = microtime(true);
        if ($tracer) {
            $tracer->begin_rank($this, $t0);
        }
        try {
            // note: pre_fetch is counted inside the rank() timer!
            $this->pre_fetch($stream_elements);
            $ranked = $this->rank_inner($stream_elements, $tracer);
            if (!Helpers::verify_reordered_elements($stream_elements, $ranked)) {
                // we throw this in here so it will trigger fail_rank to be called on the tracer.
                throw new IllegalRankerException(
                    'Rank contract violated by addition or removal of elements. Before there is ' .
                    count($stream_elements) .
                    'Now there is ' . count($ranked)
                );
            }
        } catch (\Exception $e) {
            if ($tracer) {
                $tracer->fail_rank($this, [$t0, microtime(true) - $t0], $e);
            }
            throw $e;
        }
        if ($tracer) {
            $tracer->end_rank($this, [$t0, microtime(true) - $t0], $ranked);
        }
        return $ranked;
    }

    /**
     * Implement this method to rank elements.
     * @param StreamElement[] $stream_elements The elements to rank.
     * @param StreamTracer|null $tracer Tracer to use for metrics and logging of ranking process.
     * @return StreamElement[] Same elements as input, reranked. It is illegal to add or remove elements during this operation.
     */
    abstract protected function rank_inner(array $stream_elements, StreamTracer $tracer = null): array;

    /**
     * A batch pre-fetch to inflate a set of stream elements or cache data need to be used in your ranker.
     * @param StreamElement[] $elements The elements need to be pre_fetched.
     * @return void
     */
    abstract protected function pre_fetch(array $elements);

    /**
     * Get the string representation of the current ranker.
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
