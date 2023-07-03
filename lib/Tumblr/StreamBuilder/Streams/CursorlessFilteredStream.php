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
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A FilteredStream without the cursor stuff. Useful for debugging!
 * It is itself a stream, which only enumerates items which pass the filter.
 * FilteredStreams support configurable backfill and over-fetching.
 */
final class CursorlessFilteredStream extends WrapStream
{
    /**
     * @var StreamFilter
     */
    private StreamFilter $filter;

    /**
     * @var int
     */
    private int $retry_count;

    /**
     * @var float
     */
    private float $overfetch_ratio;

    /**
     * @param Stream $inner The stream to filter.
     * @param StreamFilter $filter The filter to apply to the stream.
     * @param string $identity The string identifies the stream.
     * @param int|null $retry_count If fetching does not yield the requested number of elements
     * (after filtering), retry up to this many times to fetch more. The default value of 2
     * will therefore try a total of three times (two retries).
     * @param float|null $overfetch_ratio If you expect the filter has high selectivity, and the stream
     * is relatively cheap to over-enumerate, you can crank this up to preemptively over-fetch.
     * A value of zero means to not over-fetch, whereas a value of 1.0 means to fetch double the
     * number of results requested. No more than $count results will be returned.
     */
    public function __construct(
        Stream $inner,
        StreamFilter $filter,
        string $identity,
        ?int $retry_count = null,
        ?float $overfetch_ratio = null
    ) {
        if (is_null($retry_count)) {
            $retry_count = FilteredStream::DEFAULT_RETRY_COUNT;
        }
        if (is_null($overfetch_ratio)) {
            $overfetch_ratio = FilteredStream::DEFAULT_OVERFETCH_RATIO;
        }
        parent::__construct($inner, $identity);
        $this->filter = $filter;
        $this->retry_count = $retry_count;
        $this->overfetch_ratio = $overfetch_ratio;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'stream' => $this->getInner()->to_template(),
            'stream_filter' => $this->filter->to_template(),
            'retry_count' => $this->retry_count,
            'overfetch_ratio' => $this->overfetch_ratio,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $stream = $context->deserialize_required_property('stream');
        $filter = $context->deserialize_required_property('stream_filter');

        return new self(
            $stream,
            $filter,
            $context->get_current_identity(),
            $context->get_optional_property('retry_count'),
            $context->get_optional_property('overfetch_ratio')
        );
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
        return $this->_filter_rec($count, $cursor, $this->retry_count, $tracer, $option);
    }

    /**
     * Iteratively executed code block to fetch and filter results.
     * @param int $want_count How many elements are desired
     * @param StreamCursor|null $inner_cursor The cursor from which to fetch the inner stream.
     * @param int $depth The number of retries remaining.
     * @param StreamTracer $tracer The tracer traces filter process.
     * @param EnumerationOptions $option The option for enumeration
     * @return StreamResult
     */
    private function _filter_rec(
        int $want_count,
        ?StreamCursor $inner_cursor,
        int $depth,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $fetch_count = intval(ceil($want_count * (1.0 + max(0.0, $this->overfetch_ratio))));

        // get stuff from the inner stream:
        $inner_result = $this->getInner()->enumerate($fetch_count, $inner_cursor, $tracer, $option);
        $inner_combined_cursor = $inner_result->get_combined_cursor();

        // if we got nothing, abort. we can't recurse because the cursor would be unchanged:
        if ($inner_result->get_size() == 0) {
            $tracer && $tracer->filter_abort($this, $want_count, $inner_cursor, $depth);
            return new StreamResult(true, []);
        }

        // we got some stuff, run the filter on it:
        $filter_result = $this->filter->filter($inner_result->get_elements(), null, $tracer);
        $tracer && $tracer->filter_apply($this, $inner_result->get_size(), $filter_result);

        // we only care about the retained elements
        $retained = array_map(function (StreamElement $e) {
            return new DerivedStreamElement($e, $this->get_identity(), $e->get_cursor());
        }, $filter_result->get_retained());

        // decide whether to terminate or retry:
        $inner_exhausted = $inner_result->is_exhaustive() || is_null($inner_combined_cursor);
        if ($depth <= 0 || count($retained) >= $want_count || $inner_exhausted) {
            // because of over-fetching, even if the inner result was exhaustive, we might not use all of it, so we need to
            // trust that flag only if we will return everything (i.e. it provided us no more than what we wanted):
            $is_exhaustive = $inner_exhausted && (count($retained) <= $want_count);
            $tracer && $tracer->filter_terminate($this, $inner_cursor, $depth, $is_exhaustive);

            // we're done, return all the elements.
            return new StreamResult($is_exhaustive, array_splice($retained, 0, $want_count));
        } else {
            // not done yet. need to figure out the next cursor and filter state with which to recurse!
            // the retry cursor needs to be based on this iteration, because streams need to make progress:
            $retry_cursor = StreamCursor::combine_all([$inner_cursor, $inner_combined_cursor]);

            $tracer && $tracer->filter_retry($this, $inner_cursor, $retry_cursor, $depth, $want_count, $inner_result->get_size(), count($retained));

            return StreamResult::prepend($retained, $this->_filter_rec(
                $want_count - count($retained),
                $retry_cursor,
                $depth - 1,
                $tracer
            ));
        }
    }

    /**
     * @inheritDoc
     */
    protected function can_enumerate_with_time_range(): bool
    {
        return $this->getInner()->can_enumerate_with_time_range();
    }

    /**
     * @param string $query_string Query.
     * @return void
     */
    public function setQueryString(string $query_string)
    {
        $inner_stream = $this->getInner();
        if (method_exists($inner_stream, 'setQueryString')) {
            $inner_stream->setQueryString($query_string);
        } else {
            StreamBuilder::getDependencyBag()
                ->getLog()
                ->warning('Trying to fetch posts from stream without setting the query string');
        }
    }
}
