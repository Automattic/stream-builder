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

namespace Tumblr\StreamBuilder\StreamTracers;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\Identifiable;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\SignalFetchers\SignalBundle;
use Tumblr\StreamBuilder\SignalFetchers\SignalFetcher;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilterResult;
use Tumblr\StreamBuilder\StreamFilters\StreamFilter;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\ConcatenatedStream;
use Tumblr\StreamBuilder\Streams\PrependedStream;
use Tumblr\StreamBuilder\Streams\SizeLimitedStream;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\Streams\StreamCombiner;
use Tumblr\StreamBuilder\Streams\StreamMixer;
use Tumblr\StreamBuilder\Streams\WrapStream;

/**
 * A helper which provides context-specific callbacks triggered at various stages of the stream enumeration pipeline.
 * Used for adding logging, debugging, metrics, and error-reporting to an otherwise pure stream-processing pipeline.
 */
abstract class StreamTracer
{
    /** @var string Valid categories for generic stream tracers */
    public const CATEGORY_ENUMERATE = 'enumerate';
    public const CATEGORY_FILTER = 'filter';
    public const CATEGORY_PLAN_INJECTION = 'plan_injection';
    public const CATEGORY_SIGNAL_FETCH = 'signal_fetch';
    public const CATEGORY_RANK = 'rank';
    public const CATEGORY_SERVICE_CALL = 'service_call';
    public const CATEGORY_APPLY_MIXING_RULE = 'apply_mixing_rule';

    /** @var string Valid events for generic stream tracers */
    public const EVENT_BEGIN = 'begin';
    public const EVENT_END = 'end';
    public const EVENT_FAIL = 'fail';
    public const EVENT_SKIP = 'skip';
    public const EVENT_ABORT = 'abort';
    public const EVENT_TERMINATE = 'terminate';
    public const EVENT_RETRY = 'retry';
    public const EVENT_APPLY = 'apply';
    public const EVENT_RELEASE = 'release';
    public const EVENT_RELEASE_ALL = 'release_all';
    public const EVENT_CACHE_HIT = 'cache_hit';
    public const EVENT_CACHE_MISS = 'cache_miss';
    public const EVENT_EXHAUSTIVE = 'exhaustive';

    /** @var string Valid meta column name for generic stream tracers, only some common ones. */
    public const META_CURSOR = 'cursor';
    public const META_RESULT = 'result';
    public const META_EXCEPTION = 'exception';
    public const META_COUNT = 'count';
    public const META_IS_EXHAUSTED = 'is_exhausted';
    public const META_TARGET = 'target';
    public const META_DETAIL = 'meta_detail';
    public const META_QUERY = 'query';
    public const META_FILTER_CODE = 'filter_code';
    public const META_EXTRA = 'extra';
    public const META_SERVICE_NAME = 'srv_name';

    /**
     * @var [string => mixed] This is supposed to store any debug info in addition to the regular events.
     *  Key is supposed to be sender identity (with classname).
     */
    private static $debug_info = [];

    /**
     * Called when an enumeration request is issued to a stream.
     * @param Stream $stream The stream being enumerated.
     * @param int $count The number of results requested.
     * @param StreamCursor|null $cursor The cursor provided to the enumeration.
     * @return void
     */
    final public function begin_enumerate(
        Stream $stream,
        int $count,
        ?StreamCursor $cursor = null
    ): void {
        $meta_array = [
            static::META_COUNT => $count,
        ];
        if ($cursor) {
            $meta_array[static::META_CURSOR] = $cursor;
        }

        $this->trace_event(
            static::CATEGORY_ENUMERATE,
            $stream,
            static::EVENT_BEGIN,
            [],
            $meta_array
        );
    }

    /**
     * Called when an enumeration request completes for a stream.
     * @param Stream $stream The stream that was enumerated.
     * @param int $count The number of items returned.
     * @param StreamResult $result The result of enumeration.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order).
     * @return void
     */
    final public function end_enumerate(
        Stream $stream,
        int $count,
        StreamResult $result,
        array $timing
    ): void {
        $this->trace_event(
            static::CATEGORY_ENUMERATE,
            $stream,
            static::EVENT_END,
            $timing,
            [
                static::META_COUNT => $count,
                static::META_RESULT => $result,
            ]
        );
        if ($result->is_exhaustive() &&
            !($stream instanceof WrapStream ||
            $stream instanceof SizeLimitedStream ||
            $stream instanceof PrependedStream ||
            $stream instanceof ConcatenatedStream ||
            $stream instanceof StreamMixer ||
            $stream instanceof StreamCombiner)
        ) {
            $this->trace_event(
                static::CATEGORY_ENUMERATE,
                $stream,
                static::EVENT_EXHAUSTIVE,
            );
        }
    }

    /**
     * Called when an exception occurs during stream enumeration.
     * @param Stream $stream The stream that was enumerated.
     * @param int $count The number of items requested.
     * @param StreamCursor|null $cursor The cursor provided to the enumeration.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param \Exception $e The exception that occurred.
     * @return void
     */
    final public function fail_enumerate(
        Stream $stream,
        int $count,
        ?StreamCursor $cursor,
        array $timing,
        \Exception $e
    ): void {
        $meta_array = [
            static::META_COUNT => $count,
            static::META_EXCEPTION => $e->getTraceAsString(),
        ];
        if ($cursor) {
            $meta_array[static::META_CURSOR] = $cursor;
        }

        $this->trace_event(
            static::CATEGORY_ENUMERATE,
            $stream,
            static::EVENT_FAIL,
            $timing,
            $meta_array
        );
    }

    /**
     * Called when an enumeration request is not eligible.
     * @param Stream $stream The stream that being enumerated.
     * @param int $count The number of results requested.
     * @param StreamCursor|null $cursor The cursor provided to the enumeration.
     * @return void
     */
    final public function skip_enumerate(
        Stream $stream,
        int $count,
        ?StreamCursor $cursor = null
    ): void {
        $meta_array = [
            static::META_COUNT => $count,
        ];
        if ($cursor) {
            $meta_array[static::META_CURSOR] = $cursor;
        }

        $this->trace_event(
            static::CATEGORY_ENUMERATE,
            $stream,
            static::EVENT_SKIP,
            [],
            $meta_array
        );
    }

    /**
     * Called when stream-filtering terminates early due to enumerating no upstream results.
     * @param Stream $stream The stream performing the filtering, usually a FilteredStream.
     * @param int $want_count The number of items desired.
     * @param StreamCursor|null $cursor The cursor
     * @param int $depth The current depth when the event occurred.
     * @return void
     */
    final public function filter_abort(
        Stream $stream,
        int $want_count,
        ?StreamCursor $cursor,
        int $depth
    ): void {
        $meta_array = [
            static::META_COUNT => $want_count,
            'depth' => $depth,
        ];
        if ($cursor) {
            $meta_array[static::META_CURSOR] = $cursor;
        }

        $this->trace_event(
            static::CATEGORY_FILTER,
            $stream,
            static::EVENT_ABORT,
            [],
            $meta_array
        );
    }

    /**
     * Called when stream-filtering terminates early due to filter un-enabled.
     * @param StreamFilter $filter The stream performing the filtering, usually a FilteredStream.
     * @return void
     */
    final public function filter_skip(StreamFilter $filter): void
    {
        $this->trace_event(
            static::CATEGORY_FILTER,
            $filter,
            static::EVENT_SKIP,
            [],
            []
        );
    }

    /**
     * Called when stream-filtering terminates normally.
     * @param Stream $stream The stream performing the filtering, usually a FilteredStream.
     * @param StreamCursor|null $cursor The input cursor which caused termination.
     * @param int $depth The current depth when the event occurred.
     * @param bool $is_exhaustive Whether the filtering exhausted the source stream.
     * @return void
     */
    final public function filter_terminate(
        Stream $stream,
        ?StreamCursor $cursor,
        int $depth,
        bool $is_exhaustive
    ): void {
        $meta_array = [
            'depth' => $depth,
            static::META_IS_EXHAUSTED => $is_exhaustive,
        ];
        if ($cursor) {
            $meta_array[static::META_CURSOR] = $cursor;
        }

        $this->trace_event(
            static::CATEGORY_FILTER,
            $stream,
            static::EVENT_TERMINATE,
            [],
            $meta_array
        );
    }

    /**
     * Called when stream-filtering attempts a backfill operation.
     *
     * @param Stream $stream The stream performing the filtering, usually a FilteredStream.
     * @param StreamCursor|null $cur1 The cursor on the current iteration.
     * @param StreamCursor|null $cur2 The cursor being used for the next iteration.
     * @param int $depth The current depth when the event occurred.
     * @param int $want_count The number of items desired in the current iteration.
     * @param int $raw_size The number of items fetched from upstream in the current iteration.
     * @param int $filtered_size The number of items retained during the current iteration.
     * @return void
     */
    final public function filter_retry(
        Stream $stream,
        ?StreamCursor $cur1,
        ?StreamCursor $cur2,
        int $depth,
        int $want_count,
        int $raw_size,
        int $filtered_size
    ): void {
        $meta_array = [
            'depth' => $depth,
            'raw_size' => $raw_size,
            'filtered_size' => $filtered_size,
            static::META_COUNT => $want_count,
        ];
        if ($cur1) {
            $meta_array['cursor1'] = $cur1;
        }
        if ($cur1) {
            $meta_array['cursor2'] = $cur2;
        }

        $this->trace_event(
            static::CATEGORY_FILTER,
            $stream,
            static::EVENT_RETRY,
            [],
            $meta_array
        );
    }

    /**
     * Called when stream-filtering applies a filter to retrieved results.
     * @param Stream $stream The stream performing the filtering, usually a FilteredStream.
     * @param int $in_count The number of items being filtered.
     * @param StreamFilterResult $filter_result The result of filtering.
     * @return void
     */
    final public function filter_apply(
        Stream $stream,
        int $in_count,
        StreamFilterResult $filter_result
    ): void {
        $this->trace_event(
            static::CATEGORY_FILTER,
            $stream,
            static::EVENT_APPLY,
            [],
            [
                static::META_COUNT => $in_count,
                static::META_RESULT => $filter_result,
            ]
        );
    }

    /**
     * Called when a filter starts filter an array of stream elements.
     * @param StreamFilter $filter The stream filter is applied.
     * @param int $in_count The number of items being filtered.
     * @return void
     */
    final public function begin_filter(StreamFilter $filter, int $in_count): void
    {
        $this->trace_event(
            static::CATEGORY_FILTER,
            $filter,
            static::EVENT_BEGIN,
            [],
            [
                static::META_COUNT => $in_count,
            ]
        );
    }

    /**
     * Called when a filter completes the filtering process.
     * @param StreamFilter $filter The stream filter is applied.
     * @param int $released_count The number of items being filtered.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @return void
     */
    final public function end_filter(
        StreamFilter $filter,
        int $released_count,
        array $timing
    ): void {
        $this->trace_event(
            static::CATEGORY_FILTER,
            $filter,
            static::EVENT_END,
            $timing,
            [
                static::META_COUNT => $released_count,
            ]
        );
    }

    /**
     * Called when a filter fails to complete the filtering process.
     * @param StreamFilter $filter The stream filter is applied.
     * @param int $in_count The number of items being filtered.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param \Exception $e The exception thrown during injection-planning.
     * @return void
     */
    final public function fail_filter(
        StreamFilter $filter,
        int $in_count,
        array $timing,
        \Exception $e
    ): void {
        $this->trace_event(
            static::CATEGORY_FILTER,
            $filter,
            static::EVENT_FAIL,
            $timing,
            [
                static::META_COUNT => $in_count,
                static::META_EXCEPTION => $e->getTraceAsString(),
            ]
        );
    }

    /**
     * Called when a stream element is released.
     * @param StreamFilter $filter The stream filter that released current element.
     * @param StreamElement $element The wrapper of which element being filtered out and filter info.
     * @return void
     */
    public function release_element(
        StreamFilter $filter,
        StreamElement $element
    ): void {
        $ori_e = $element->get_original_element();
        $meta = [
            // Careful about this, META_TARGET needs to be bounded for ticking sake
            static::META_TARGET => Helpers::get_unqualified_class_name($ori_e),
        ];
        $meta[static::META_DETAIL] = $ori_e->__toString();

        $element_logging_meta = $ori_e->get_debug_info()[StreamFilter::LOGGING_HEADER] ?? [];
        $meta = array_merge($meta, $element_logging_meta);
        $this->trace_event(
            static::CATEGORY_FILTER,
            $filter,
            static::EVENT_RELEASE,
            [],
            $meta
        );
    }

    /**
     * Called when a filter releases all the elements.
     * @param StreamFilter $filter The stream filter that released the elements.
     * @param int $count The amount of elements released.
     * @return void
     */
    public function release_all_elements(StreamFilter $filter, int $count)
    {
        $meta = [static::META_COUNT => $count];
        $this->trace_event(
            static::CATEGORY_FILTER,
            $filter,
            static::EVENT_RELEASE_ALL,
            [],
            $meta
        );
    }

    /**
     * Called when an injector is asked to plan an injection.
     * @param StreamInjector $injector The injector responsible for planning.
     * @param int $page_size The page size into which items will be injected.
     * @param array|null $state The injector state, if any.
     * @return void
     * @throws \JsonException Throws exception if we cannot JSON stringify the state
     */
    final public function begin_plan_injection(
        StreamInjector $injector,
        int $page_size,
        ?array $state = null
    ): void {
        $this->trace_event(
            static::CATEGORY_PLAN_INJECTION,
            $injector,
            static::EVENT_BEGIN,
            [],
            [
                static::META_COUNT => $page_size,
                'state' => Helpers::json_encode($state),
            ]
        );
    }

    /**
     * Called when an injector completes the planning of an injection.
     * @param StreamInjector $injector The injector responsible for planning.
     * @param int $injected_count How many items been planned to be injected.
     * @param InjectionPlan $result The result of the injection.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @return void
     */
    final public function end_plan_injection(
        StreamInjector $injector,
        int $injected_count,
        InjectionPlan $result,
        array $timing
    ): void {
        $this->trace_event(
            static::CATEGORY_PLAN_INJECTION,
            $injector,
            static::EVENT_END,
            $timing,
            [
                static::META_COUNT => $injected_count,
                static::META_RESULT => $result,
            ]
        );
    }

    /**
     * Called when an injector throws an exception while planning an injection.
     * @param StreamInjector $injector The injector responsible for planning.
     * @param int $page_size The page size into which items will be injected.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param \Exception $e The exception thrown during injection-planning.
     * @return void
     */
    final public function fail_plan_injection(
        StreamInjector $injector,
        int $page_size,
        array $timing,
        \Exception $e
    ): void {
        $this->trace_event(
            static::CATEGORY_PLAN_INJECTION,
            $injector,
            static::EVENT_FAIL,
            $timing,
            [
                static::META_COUNT => $page_size,
                static::META_EXCEPTION => $e->getTraceAsString(),
            ]
        );
    }

    /**
     * Called when a signal fetcher is told to fetch signals.
     * @param SignalFetcher $fetcher The signal fetcher being called.
     * @return void
     */
    final public function begin_signal_fetch(SignalFetcher $fetcher): void
    {
        $this->trace_event(
            static::CATEGORY_SIGNAL_FETCH,
            $fetcher,
            static::EVENT_BEGIN
        );
    }

    /**
     * Called when a signal fetcher fails to fetch signals because an exception is thrown.
     * @param SignalFetcher $fetcher The signal fetcher being called.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param \Exception $e The exception thrown causing the failure.
     * @return void
     */
    final public function fail_signal_fetch(
        SignalFetcher $fetcher,
        array $timing,
        \Exception $e
    ): void {
        $this->trace_event(
            static::CATEGORY_SIGNAL_FETCH,
            $fetcher,
            static::EVENT_FAIL,
            $timing,
            [
                static::META_EXCEPTION => $e->getTraceAsString(),
            ]
        );
    }

    /**
     * Called when a signal fetcher completes fetching signals.
     * @param SignalFetcher $fetcher The signal fetcher being called.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param SignalBundle $bundle The bundle of signals returned as a result of fetching.
     * @return void
     */
    final public function end_signal_fetch(
        SignalFetcher $fetcher,
        array $timing,
        SignalBundle $bundle
    ): void {
        $this->trace_event(
            static::CATEGORY_SIGNAL_FETCH,
            $fetcher,
            static::EVENT_END,
            $timing,
            [
                'signal_bundle' => $bundle,
            ]
        );
    }

    /**
     * Called when a ranker begins ranking.
     * @param StreamRanker $ranker The ranker.
     * @return void
     */
    public function begin_rank(StreamRanker $ranker): void
    {
        $this->trace_event(
            static::CATEGORY_RANK,
            $ranker,
            static::EVENT_BEGIN
        );
    }

    /**
     * Called when a ranker fails at ranking.
     * @param StreamRanker $ranker The ranker.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param \Exception $e The exception that caused failure.
     * @return void
     */
    final public function fail_rank(
        StreamRanker $ranker,
        array $timing,
        \Exception $e
    ): void {
        $this->trace_event(
            static::CATEGORY_RANK,
            $ranker,
            static::EVENT_FAIL,
            $timing,
            [
                static::META_EXCEPTION => $e->getTraceAsString(),
            ]
        );
    }

    /**
     * Called when a ranker completes ranking.
     * @param StreamRanker $ranker The ranker.
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param StreamElement[] $ranked_elements The elements as ordered after ranking.
     * @return void
     * @throws \JsonException Throws exception if we cannot JSON stringify the state
     */
    final public function end_rank(
        StreamRanker $ranker,
        array $timing,
        array $ranked_elements
    ): void {
        $this->trace_event(
            static::CATEGORY_RANK,
            $ranker,
            static::EVENT_END,
            $timing,
            [
                static::META_RESULT => Helpers::json_encode(array_map(function (StreamElement $e) {
                    return (string) $e;
                }, $ranked_elements)),
            ]
        );
    }

    /**
     * Stream enumeration hit cache.
     * @param Stream $stream The Stream that contains the cache logic.
     * @param array $timing Zero indexed tuple of start time and duration of the operation (in that order)
     * @return void
     */
    final public function enumerate_cache_hit(Stream $stream, array $timing): void
    {
        $this->trace_event(
            static::CATEGORY_ENUMERATE,
            $stream,
            static::EVENT_CACHE_HIT,
            $timing
        );
    }

    /**
     * Stream enumeration miss cache.
     * @param Stream $stream The Stream that contains the cache logic.
     * @param array $timing Zero indexed tuple of start time and duration of the operation (in that order)
     * @return void
     */
    final public function enumerate_cache_miss(Stream $stream, array $timing): void
    {
        $this->trace_event(
            static::CATEGORY_ENUMERATE,
            $stream,
            static::EVENT_CACHE_MISS,
            $timing
        );
    }

    /**
     * Called when calling an external service
     * @param Stream $stream The Stream that contains the search logic
     * @param StreamCursor|null $cursor The cursor provided for search pagination
     * @param string|null $service_name The name of the service
     * @return void
     */
    final public function begin_service_call(Stream $stream, ?StreamCursor $cursor = null, ?string $service_name = null): void
    {
        $this->trace_event(
            static::CATEGORY_SERVICE_CALL,
            $stream,
            static::EVENT_BEGIN,
            [],
            [
                static::META_CURSOR => $cursor,
                static::META_SERVICE_NAME => $service_name,
            ]
        );
    }

    /**
     * Called when get response from calling an external service
     * @param Stream $stream The Stream that contains the logic
     * @param array $timing Zero indexed tuple of start time and duration of the operation (in that order)
     * @param array $response Service call response
     * @param string|null $service_name The name of the service
     * @return void
     */
    final public function end_service_call(Stream $stream, array $timing, array $response, ?string $service_name = null): void
    {
        $this->trace_event(
            static::CATEGORY_SERVICE_CALL,
            $stream,
            static::EVENT_END,
            $timing,
            [
                static::META_RESULT => $response,
                static::META_SERVICE_NAME => $service_name,
            ]
        );
    }

    /**
     * Called when service call failed.
     * @param Stream $stream The Stream that contains the logic
     * @param array $timing Zero indexed tuple of start time and duration of the operation (in that order)
     * @param \Throwable $throwable Service call exception, if available.
     * @param string|null $service_name The name of the service
     * @return void
     */
    final public function fail_service_call(
        Stream $stream,
        array $timing,
        \Throwable $throwable,
        ?string $service_name = null
    ): void {
        $this->trace_event(
            static::CATEGORY_SERVICE_CALL,
            $stream,
            static::EVENT_FAIL,
            $timing,
            [
                static::META_EXCEPTION => $throwable,
                static::META_SERVICE_NAME => $service_name,
            ]
        );
    }
    /**
     * Trace an array of debug info to static array.
     * @param Identifiable $sender The debug info belong to the sender.
     * @param array $debug_info The debug info array.
     * @return void
     */
    final public function trace_debug(Identifiable $sender, array $debug_info): void
    {
        $identity = $sender->get_identity(true);
        self::$debug_info[$identity] = array_merge((self::$debug_info[$identity] ?? []), $debug_info);
    }

    /**
     * Retrieves the debug info of the post with the given ID.
     * @param Identifiable $sender The debug info belong to the sender.
     * @return array
     */
    public static function get_debug_info(Identifiable $sender): array
    {
        $identity = $sender->get_identity(true);
        return self::$debug_info[$identity] ?? [];
    }

    /**
     * Called when $executor $event_name to $event_category .
     *
     * @param string $event_category Operation that needs to be traced.
     * @param Identifiable $sender The sender who sends this event.
     * @param string $event_name Begin/End/Fail/Skip/....
     * @param array $timing Zero indexed tuple of the start time and duration of the operation (in that order)
     * @param mixed[] $meta Other info that needs to be traced. Usually used for debug, use carefully.
     *   For most of the case you should just pass in string, or at least needs to implement __toString
     * @return void
     */
    abstract public function trace_event(
        string $event_category,
        Identifiable $sender,
        string $event_name,
        ?array $timing = [],
        ?array $meta = []
    ): void;
}
