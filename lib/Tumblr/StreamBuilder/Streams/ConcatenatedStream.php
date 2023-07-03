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
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\ConcatenatedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * This concatenates elements from streams in order. Will not enumerate a next stream until
 * the current stream is exhausted.
 */
class ConcatenatedStream extends Stream
{
    /**
     * Concatenated streams.
     * @var Stream[]
     */
    private $streams;

    /**
     * If true, pass in current stream's enumeration state as EnumerationOption for next stream
     * Current stream's enumeration state defined as Timestamp of last element in StreamResult
     * @var bool
     */
    public bool $stateful_concatenate;

    /**
     * @param Stream[] $streams Streams to be concatenated, in order
     * @param string $identity The unique identity for ConcatenatedStream.
     * @param bool $stateful_concatenate If pass in updated enumeration option
     */
    public function __construct(array $streams, string $identity, bool $stateful_concatenate = false)
    {
        $this->streams = array_filter($streams, function ($elem) {
            return $elem instanceof Stream;
        });
        parent::__construct($identity);
        $this->stateful_concatenate = $stateful_concatenate;
    }

    /**
     * @return Stream[]
     */
    public function getStreams(): array
    {
        return $this->streams;
    }

    /**
     * @inheritDoc
     */
    protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        // if this stream does not contain any stream, should return empty result
        if (empty($this->streams)) {
            return new StreamResult(true, []);
        }
        if (!empty($cursor)) {
            // if passed in cursor is null, means wants from the start of the stream
            if (!($cursor instanceof ConcatenatedStreamCursor)) {
                throw new InappropriateCursorException($this, $cursor);
            }
            // check if cursor's source_index is valid
            if ($cursor->get_source_index() >= count($this->streams) || $cursor->get_source_index() < 0) {
                throw new InappropriateCursorException($this, $cursor);
            }
            $current_index = $cursor->get_source_index();
        } else {
            $current_index = 0;
        }
        $results = [];
        $exhaust_flag = false;
        while ($current_index < count($this->streams)) {
            $cur_stream = $this->streams[$current_index];
            $cur_stream_cursor = ($cursor != null && $current_index == $cursor->get_source_index()) ? $cursor->get_source_cursor() : null;
            try {
                $cur_stream_res = $cur_stream->enumerate($count - count($results), $cur_stream_cursor, $tracer, $option);
            } catch (\Throwable $t) {
                // for individual stream failure we will degrade service by returning empty result
                // instead of throwing exception that would cause whole request to fail or skip other stream's enumeration.
                $log = StreamBuilder::getDependencyBag()->getLog();
                $log->exception($t, $this->get_identity());
                $cur_stream_res = new StreamResult(true, []);
            }
            $exhaust_flag = $cur_stream_res->is_exhaustive() && $current_index >= (count($this->streams) - 1);

            foreach ($cur_stream_res->get_elements() as $elem) {
                $new_cursor = new ConcatenatedStreamCursor($current_index, $elem->get_cursor());
                $results[] = new DerivedStreamElement($elem, $this->get_identity(), $new_cursor);
            }

            if (count($results) >= $count || !$cur_stream_res->is_exhaustive()) {
                // if count is already met
                break;
            } else {
                // we need to move to next stream
                $current_index++;
                // Check if should pass in current stream's enumeration state
                if ($this->stateful_concatenate && count($results) > 0) {
                    $last_elem = $results[count($results) - 1]->get_original_element();
                    if ($last_elem instanceof ChronologicalStreamElement) {
                        try {
                            $last_ts = $last_elem->get_timestamp_ms();
                            $option = new EnumerationOptions($last_ts, null);
                        } catch (\Exception $e) {
                            // Skip new EnumerationOptions, if fetch element's timestamp failed
                        }
                    }
                }
            }
        }
        return new StreamResult($exhaust_flag, array_slice($results, 0, $count));
    }


    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'streams' => array_map(function ($s) {
                /** @var Stream $s */
                return $s->to_template();
            }, $this->streams),
            'stateful' => $this->stateful_concatenate,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $stream_templates = $context->get_required_property('streams');

        if (!is_array($stream_templates)) {
            throw new \InvalidArgumentException('The \'streams\' key on ConcatenatedStream should be an array containing streams, not a value');
        }

        if (isset($stream_templates['_type'])) {
            // Make sure that "streams" is an ordered, not associative array
            throw new \InvalidArgumentException('The \'streams\' key on ConcatenatedStream should be an array containing streams, not a stream');
        }

        $streams = [];
        foreach ($stream_templates as $i => $stream_template) {
            $new_context = $context->derive($stream_template, sprintf('streams/%d', $i));
            /** @var Stream $s */
            $s = StreamSerializer::from_template($new_context);
            $streams[] = $s;
        }
        return new self($streams, $context->get_current_identity(), $context->get_optional_property('stateful', false));
    }

    /**
     * @param string $query Query string
     * @return void
     */
    public function setQueryString(string $query)
    {
        foreach ($this->streams as $stream) {
            if (method_exists($stream, 'setQueryString')) {
                $stream->setQueryString($query);
            }
        }
    }
}
