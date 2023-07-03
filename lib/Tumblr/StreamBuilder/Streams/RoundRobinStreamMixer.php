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

namespace Tumblr\StreamBuilder\Streams;

use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * This stream mixer performs a round robin based stream mixing strategy.
 * It takes a main stream and an array of minor streams in the constructor.
 * Main stream generates stream elements base on certain distribution provided.
 * Minor streams fulfill rest empty positions in a round robin logic.
 */
abstract class RoundRobinStreamMixer extends StreamMixer
{
    /**
     * @var Stream
     */
    protected $main;

    /**
     * @var Stream[]
     */
    protected $minors;

    /**
     * RoundRobinStreamMixer constructor.
     * @param Stream $main The main stream.
     * @param Stream[] $minors The minor streams array, type of Stream.
     * @param StreamInjector $injector The injector used during enumeration.
     * @param string $identity The identity.
     * @throws \InvalidArgumentException While input minor stream is not a Stream type.
     */
    public function __construct(Stream $main, array $minors, StreamInjector $injector, string $identity)
    {
        $this->main = $main;
        foreach ($minors as $minor) {
            if (!($minor instanceof Stream)) {
                throw new \InvalidArgumentException(sprintf(
                    'Minor stream must be a type of Stream, but get input type: %s',
                    gettype($minor)
                ));
            }
        }
        $this->minors = $minors;
        parent::__construct($injector, $identity);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $minor_templates = array_map(function (Stream $stream) {
            return $stream->to_template();
        }, $this->minors);

        return [
            '_type'                     => static::class,
            'stream_injector'           => $this->injector->to_template(),
            'stream_main'               => $this->main->to_template(),
            'stream_minors_array'       => $minor_templates,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mix(
        int $count,
        MultiCursor $cursor,
        ?StreamTracer $tracer,
        InjectionPlan $injection_plan,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $results = [];

        $main_positions = $this->get_main_stream_positions($count, $tracer);
        // at least fetch for 1 main stream element.
        $main_count = max(1, count($main_positions));
        $main_result = $this->main->enumerate($main_count, $cursor->cursor_for_stream($this->main), $tracer, $option);
        $main_elements = array_reverse($main_result->get_elements());

        $minor_count = $count - count($main_positions); // Only request remaining positions from minor stream.
        if ($minor_count <= 0) {
            // Return main stream elements when minor streams elements are not needed.
            return new StreamResult($main_result->is_exhaustive(), array_slice($main_elements, 0, $count));
        }

        // Otherwise, combine results from minor streams.
        $minor_results = array_map(function (Stream $stream) use ($minor_count, $cursor, $tracer, $option) {
            return $stream->enumerate($minor_count, $cursor->cursor_for_stream($stream), $tracer, $option);
        }, $this->minors);
        /** @var \Generator $minor_elements_generator */
        $minor_elements_generator = $this->get_minor_generator($minor_results);

        for ($i = 0; $i < $count; $i++) {
            if (in_array($i, $main_positions, true)) {
                $results[$i] = array_pop($main_elements);
            } else {
                $results[$i] = $minor_elements_generator->current();
                $minor_elements_generator->next();
            }
        }

        // Filter out null elements generated from $minor_elements_generator.
        $results = array_filter($results);
        ksort($results);
        // Append main stream results at the end, in case minors are exhausted.
        $results = array_merge($results, $main_elements);

        return new StreamResult(
            $main_result->is_exhaustive() && !$minor_elements_generator->valid(),
            array_slice($results, 0, $count)
        );
    }

    /**
     * To get a generator of minor stream element.
     * @param StreamResult[] $minor_results The array of minor stream results.
     * @return \Generator
     */
    private function get_minor_generator(array $minor_results): \Generator
    {
        $max = max(array_map(function (StreamResult $res) {
            return $res->get_size();
        }, $minor_results));

        $elements = [];
        for ($i = 0; $i < $max; $i++) {
            foreach ($minor_results as $result) {
                if ($el = $result->get_element_at_index($i)) {
                    $elements[] = $el;
                }
            }
        }

        foreach ($elements as $el) {
            yield $el;
        }
    }

    /**
     * Given an input mixing size, return an array of positions the main stream should put element to.
     * @param int $count Mixing size.
     * @param StreamTracer|null $tracer The stream tracer.
     * @return int[] The position array.
     */
    abstract protected function get_main_stream_positions(int $count, ?StreamTracer $tracer = null): array;
}
