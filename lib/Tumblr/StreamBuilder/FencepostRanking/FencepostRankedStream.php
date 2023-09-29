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

namespace Tumblr\StreamBuilder\FencepostRanking;

use Tumblr\StreamBuilder\CurrentTimestampProvider;
use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\Interfaces\PostStreamElementInterface;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\TimestampProvider;
use const Tumblr\StreamBuilder\SECONDS_PER_MINUTE;

/**
 * Ranked stream which fenceposts for historic consistency.
 */
abstract class FencepostRankedStream extends Stream
{
    /**
     * @var string
     */
    public const DEBUG_GROUP = 'fenceposting';

    /**
     * @var string
     */
    public const DEBUG_FIELD_TIMESTAMP = 'fencepost_timestamp_ms';

    /**
     * @var string
     */
    public const DEBUG_FIELD_STRATEGY = 'source_strategy';

    /**
     * @var string
     */
    public const DEBUG_FIELD_OFFSET = 'head_offset';

    /**
     * @var Stream
     */
    protected Stream $inner;

    /**
     * @var StreamRanker
     */
    protected StreamRanker $head_ranker;

    /**
     * @var int
     */
    protected int $head_count;

    /**
     * If true, the seed (original) fencepost should be ranked, otherwise it is a chronological epoch.
     * @var bool
     */
    protected bool $rank_seed;

    /**
     * @var string
     */
    protected string $fence_id;

    /**
     * @var FencepostProvider
     */
    protected FencepostProvider $fencepost_provider;

    /**
     * @var TimestampProvider
     */
    private TimestampProvider $timestamp_provider;

    /**
     * @param Stream $inner The inner stream from which items will be enumerated.
     * @param StreamRanker $head_ranker The ranker for the head of new fenceposts.
     * @param int $head_count How many pre-ranked element to cache.
     * @param bool $rank_seed If true, the seed (original) fencepost should be ranked,
     * otherwise it is a chronological epoch.
     * @param string $identity The identity of this stream.
     * @param FencepostProvider $fencepost_provider The provider of fencepost data.
     * @param TimestampProvider|null $timestamp_provider The provider of timestamp.
     */
    public function __construct(
        Stream $inner,
        StreamRanker $head_ranker,
        int $head_count,
        bool $rank_seed,
        string $identity,
        FencepostProvider $fencepost_provider,
        ?TimestampProvider $timestamp_provider = null
    ) {
        parent::__construct($identity);

        $this->inner = $inner;
        if (!$this->inner->can_enumerate_with_time_range()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Inner stream %s of Fencepost Stream must support time range enumeration',
                    get_class($this->inner)
                )
            );
        }

        $this->head_ranker = $head_ranker;
        $this->head_count = $head_count;
        $this->rank_seed = $rank_seed;
        $this->fence_id = $this->get_fence_id_str();

        $this->timestamp_provider = $timestamp_provider ?: new CurrentTimestampProvider();
        $this->fencepost_provider = $fencepost_provider;
    }

    /**
     * Get the FencepostProvider used by this stream.
     * @return FencepostProvider
     */
    public function get_fencepost_provider(): FencepostProvider
    {
        return $this->fencepost_provider;
    }

    /**
     * Get the fence id used by this stream.
     * @return string
     */
    public function get_fence_id(): string
    {
        return $this->fence_id;
    }

    /**
     * Get the fencepost id for the specific type of fencepost
     * This is gonna be used as cache key and need to be able to differentiate each 'fence'
     * @return string
     */
    abstract protected function get_fence_id_str(): string;

    /**
     * @inheritDoc
     */
    protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        if (!(is_null($cursor) || ($cursor instanceof FencepostCursor))) {
            throw new InappropriateCursorException($this, $cursor);
        }
        /**
         * @var FencepostCursor|null $cursor The cursor to use. If null, we are exhausted!
         * @var Fencepost|null $fencepost The fencepost the cursor is pointing to.
         *     If null, means that the fencepost has been lost and we will blindly
         *     use the tail_cursor
         */
        [$fencepost, $cursor] = $this->setup_enumeration($cursor, $tracer, $option);

        if (is_null($cursor)) {
            return StreamResult::create_empty_result();
        } else {
            return $this->enumerate_rec($count, $fencepost, $cursor, $tracer, $option);
        }
    }

    /**
     * Resolve the cursor and fencepost used for enumeration.
     * @param FencepostCursor|null $in_cursor The input cursor.
     * @param StreamTracer|null $tracer The tracer used for enumeration
     * @param EnumerationOptions|null $option The option on enumeration
     * @return array [Fencepost|null, FencepostRankedStreamCursor|null]
     */
    private function setup_enumeration(FencepostCursor $in_cursor = null, StreamTracer $tracer = null, ?EnumerationOptions $option = null)
    {
        if (!is_null($in_cursor)) {
            // you already have a cursor, try to turn it into a fencepost:
            return $this->setup_enumeration_from_cursor($in_cursor);
        }

        // you didn't come in with a cursor,
        // so we need to decide if we should make a new fencepost or serve the "latest" one:
        $now_ms = $this->timestamp_provider->time_ms();
        $latest_ms = $this->fencepost_provider->get_latest_timestamp($this->fence_id);

        $epoch = $this->get_epoch();
        if (!is_null($epoch) && !is_null($latest_ms) && $epoch > $latest_ms) {
            $latest_ms = null;
            StreamBuilder::getDependencyBag()->getLog()
                ->superRateTick('fencepost_ops', ['op' => 'enum', 'action' => 'reset_latest']);
        }

        if ((!is_null($latest_ms)) && ($latest_ms >= $now_ms)) {
            // you already have a future fencepost! serve it:
            return $this->setup_enumeration_from_existing_fencepost($tracer, $now_ms, $latest_ms, $option);
        } else {
            // time has passed since your last fencepost, or there is no last fencepost. make a new one.
            return $this->setup_enumeration_from_new_fencepost($tracer, $now_ms, $latest_ms, $option);
        }
    }

    /**
     * Setup enumeration given a non-null cursor.
     * @param FencepostCursor $in_cursor The input cursor.
     * @return array [Fencepost|null, FencepostRankedStreamCursor]
     */
    private function setup_enumeration_from_cursor(FencepostCursor $in_cursor)
    {
        $fencepost = $this->fencepost_provider->get_fencepost($this->fence_id, $in_cursor->get_fencepost_timestamp_ms());
        if (!is_null($fencepost)) {
            // found the fencepost for your cursor, we are good to go!
            return [$fencepost, $in_cursor];
        }

        // your cursor references a missing fencepost id. enumeration will proceed using only
        // the tail cursor to avoid breaking your dash. we call this state the "final tail" cursor:
        return [null, FencepostCursor::create_final($in_cursor->get_tail_cursor())];
    }

    /**
     * Setup enumeration from an existing fencepost, given a null cursor.
     * @param StreamTracer|null $tracer The tracer to use for inner stream enumeration.
     * @param int $now_ms The current (new) fencepost timestamp
     * @param int $latest_ms The latest fencepost timestamp
     * @param EnumerationOptions|null $option The option on enumeration
     * @return array [Fencepost|null, FencepostRankedStreamCursor|null]
     */
    private function setup_enumeration_from_existing_fencepost(
        ?StreamTracer $tracer,
        int $now_ms,
        int $latest_ms,
        ?EnumerationOptions $option = null
    ): array {
        $fencepost = $this->fencepost_provider->get_fencepost($this->fence_id, $latest_ms);
        if (!is_null($fencepost)) {
            // cool, we have a very recent (or future) extant fencepost. let's use it:
            if ($fencepost->is_inject_fence()) {
                return [$fencepost, FencepostCursor::create_inject($now_ms, null, $fencepost->get_tail_cursor())];
            } else {
                return [$fencepost, FencepostCursor::create_head($latest_ms, 0, $fencepost->get_tail_cursor())];
            }
        }

        // we cant find the actual fencepost for that timestamp, so we need to start from scratch. pretend $latest_ms is null....
        $fencepost = $this->commit_new_fencepost($now_ms, null, $tracer, $option);
        if (!is_null($fencepost)) {
            // congratulations! we committed a new non-empty fencepost for you, serve it beginning at offset zero of its head:
            return [$fencepost, FencepostCursor::create_head($now_ms, 0, $fencepost->get_tail_cursor())];
        }

        // your dashboard is exhausted; $cursor will be returned as null.
        return [null, null];
    }

    /**
     * Setup enumeration by creating a new fencepost, given a null cursor.
     * @param StreamTracer|null $tracer The tracer to use for inner stream enumeration.
     * @param int $now_ms The current (new) fencepost timestamp
     * @param int|null $latest_ms The latest fencepost timestamp
     * @return array [Fencepost|null, FencepostRankedStreamCursor|null]
     * @param EnumerationOptions|null $option The option on enumeration
     */
    private function setup_enumeration_from_new_fencepost(
        ?StreamTracer $tracer,
        int $now_ms,
        int $latest_ms = null,
        ?EnumerationOptions $option = null
    ): array {
        $fencepost = $this->commit_new_fencepost($now_ms, $latest_ms, $tracer, $option);
        if (!is_null($fencepost)) {
            // congratulations! we committed a new non-empty fencepost for you, serve it beginning at offset zero of its head:
            if ($fencepost->is_inject_fence()) {
                return [$fencepost, FencepostCursor::create_inject($now_ms, null, $fencepost->get_tail_cursor())];
            } else {
                return [$fencepost, FencepostCursor::create_head($now_ms, 0, $fencepost->get_tail_cursor())];
            }
        }

        // nothing new since last fencepost, try to serve the previous one...
        if (is_null($latest_ms)) {
            // no previous fencepost timestamp, dashboard exhausted.
            return [null, null];
        }

        // get previous fencepost, fallback to enumerate from current timestamp, if that fencepost is cache evicted.
        $fencepost = $this->fencepost_provider->get_fencepost($this->fence_id, $latest_ms) ?:
            $this->commit_new_fencepost($now_ms, null, $tracer, $option);
        if (is_null($fencepost)) {
            // cant find previous fencepost, also can't start enumerate anything from now, dashboard exhausted.
            return [null, null];
        }

        // ok, we found a previous fencepost to use, serve it beginning at offset zero of its head:
        if ($fencepost->is_inject_fence()) {
            return [$fencepost, FencepostCursor::create_inject($now_ms, null, $fencepost->get_tail_cursor())];
        } else {
            return [$fencepost, FencepostCursor::create_head($latest_ms, 0, $fencepost->get_tail_cursor())];
        }
    }

    /**
     * Recursive fencepost-traversal enumerator. This is gonna get weird.
     * @param int $remain_count How many items are still needed.
     * @param Fencepost|null $fencepost The fencepost we are in, or null if it could not be found.
     * @param FencepostCursor $cursor The cursor into said fencepost.
     * @param StreamTracer|null $tracer The tracer to use.
     * @param EnumerationOptions|null $option The option on enumeration
     * @throws \LogicException If the cursor has an invalid region.
     * @return StreamResult
     */
    protected function enumerate_rec(
        int $remain_count,
        ?Fencepost $fencepost,
        FencepostCursor $cursor,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        if ($option && !is_null($option->get_enumerate_after_ts()) &&
            $cursor->get_fencepost_timestamp_ms() < $option->get_enumerate_after_ts()) {
            return StreamResult::create_empty_result();
        }

        // if we have no fencepost, we enumerate the inner stream directly...
        if (is_null($fencepost)) {
            return $this->enumerate_final($remain_count, $cursor, $tracer, $option);
        }

        // otherwise, we have a current fencepost. do an enumerate step:
        switch ($cursor->get_region()) {
            case FencepostCursor::REGION_HEAD:
                return $this->enumerate_rec_head($remain_count, $fencepost, $cursor, $option);
            case FencepostCursor::REGION_TAIL:
                return $this->enumerate_rec_tail($remain_count, $fencepost, $cursor, $tracer, $option);
            case FencepostCursor::REGION_INJECT:
                return $this->enumerate_rec_inject($remain_count, $fencepost, $cursor, $tracer, $option);
            default:
                throw new \LogicException(sprintf('Unknown fencepost region %s', $cursor->get_region()));
        }
    }

    /**
     * Enumerate step from a fencepost tail.
     * @param int $remain_count How many items are needed.
     * @param Fencepost $fencepost The current fencepost.
     * @param FencepostCursor $cursor The current cursor.
     * @param StreamTracer|null $tracer The tracer to use.
     * @param EnumerationOptions|null $option The option on enumeration
     * @return StreamResult
     */
    private function enumerate_rec_tail(
        int $remain_count,
        Fencepost $fencepost,
        FencepostCursor $cursor,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $after_ts_exclusive = $fencepost->get_next_timestamp_ms();
        $early_cut_fence_tail = false;
        if ($option && !is_null($option->get_enumerate_after_ts()) &&
            (is_null($after_ts_exclusive) || $option->get_enumerate_after_ts() > $after_ts_exclusive)) {
            $after_ts_exclusive = $option->get_enumerate_after_ts();
            $early_cut_fence_tail = true;
        }
        $inner_res = $this->enumerate_inner_between(
            $cursor->get_fencepost_timestamp_ms(),
            $after_ts_exclusive,
            $remain_count,
            $cursor->get_tail_cursor(),
            $tracer
        );

        $tail_elems = $inner_res->get_elements();

        /** @var DerivedStreamElement[] $derived_elems */
        $derived_elems = [];
        foreach ($tail_elems as $tail_elem) {
            $derived_elem = new DerivedStreamElement(
                $tail_elem,
                $this->get_identity(),
                FencepostCursor::create_tail($cursor->get_fencepost_timestamp_ms(), $tail_elem->get_cursor())
            );
            $derived_elem->add_debug_info(self::DEBUG_GROUP, self::DEBUG_FIELD_TIMESTAMP, $cursor->get_fencepost_timestamp_ms());
            $derived_elem->add_debug_info(self::DEBUG_GROUP, self::DEBUG_FIELD_STRATEGY, 'tail');
            $derived_elems[] = $derived_elem;
        }

        $tail_retrieve_count = count($derived_elems);

        if ($tail_retrieve_count >= $remain_count) {
            // we're done.
            return new StreamResult(false, $derived_elems);
        } else {
            if ($early_cut_fence_tail) {
                return StreamResult::create_empty_result();
            }

            $next_fencepost = null;
            if ($next_fencepost_timestamp_ms = $fencepost->get_next_timestamp_ms()) {
                $next_fencepost = $this->fencepost_provider->get_fencepost($this->fence_id, $next_fencepost_timestamp_ms);
            }

            if (is_null($next_fencepost)) {
                // no next fencepost, we continue with the final tail.
                // if there's nothing in between two fencepost, and the older fencepost is cache evicted,
                // we fallback to use the tail cursor for enumerate_final
                $final_tail_cursor = $inner_res->get_combined_cursor() ?: $cursor->get_tail_cursor();
                if (is_null($final_tail_cursor)) {
                    StreamBuilder::getDependencyBag()->getLog()
                        ->superRateTick('fencepost_ops', ['op' => 'enum', 'action' => 'tail_to_null']);
                    return StreamResult::create_empty_result();
                } else {
                    StreamBuilder::getDependencyBag()->getLog()
                        ->superRateTick('fencepost_ops', ['op' => 'enum', 'action' => 'tail_to_final']);
                    return StreamResult::prepend($derived_elems, $this->enumerate_final(
                        $remain_count - $tail_retrieve_count,
                        FencepostCursor::create_final($final_tail_cursor),
                        $tracer
                    ));
                }
            } else {
                if ($next_fencepost->is_inject_fence()) {
                    $next_cursor = FencepostCursor::create_inject(
                        $next_fencepost_timestamp_ms,
                        null,
                        $next_fencepost->get_tail_cursor()
                    );
                } else {
                    $next_cursor = FencepostCursor::create_head(
                        $next_fencepost_timestamp_ms,
                        0,
                        $next_fencepost->get_tail_cursor()
                    );
                }
                return StreamResult::prepend($derived_elems, $this->enumerate_rec(
                    $remain_count - $tail_retrieve_count,
                    $next_fencepost,
                    $next_cursor,
                    $tracer,
                    $option
                ));
            }
        }
    }


    /**
     * Enumerate step from a fencepost inject region
     * @param int $remain_count How many items are needed.
     * @param Fencepost $fencepost The current fencepost.
     * @param FencepostCursor $cursor The current cursor.
     * @param StreamTracer|null $tracer The tracer to use.
     * @param EnumerationOptions|null $option The option on enumeration
     * @return StreamResult
     */
    protected function enumerate_rec_inject(
        int $remain_count,
        Fencepost $fencepost,
        FencepostCursor $cursor,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        // Override by injection enabled fencepost
        return StreamResult::create_empty_result();
    }

    /**
     * Enumerate step from a fencepost head.
     * @param int $remain_count How many items are needed.
     * @param Fencepost $fencepost The current fencepost.
     * @param FencepostCursor $cursor The current cursor.
     * @param EnumerationOptions|null $option The option on enumeration
     * @return StreamResult
     */
    private function enumerate_rec_head(
        int $remain_count,
        Fencepost $fencepost,
        FencepostCursor $cursor,
        ?EnumerationOptions $option = null
    ): StreamResult {
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'enum', 'action' => 'head']);
        $head_elems = array_slice($fencepost->get_head(), $cursor->get_head_offset(), $remain_count, true);
        if (empty($head_elems)) {
            // switch to tail!
            return $this->enumerate_rec(
                $remain_count,
                $fencepost,
                FencepostCursor::create_tail($cursor->get_fencepost_timestamp_ms(), $cursor->get_tail_cursor()),
                null,
                $option
            );
        } else {
            $derived_elems = [];
            foreach ($head_elems as $i => $head_elem) {
                $derived_elem = new DerivedStreamElement(
                    $head_elem,
                    $this->get_identity(),
                    FencepostCursor::create_head($cursor->get_fencepost_timestamp_ms(), $i + 1, $cursor->get_tail_cursor())
                );
                $derived_elem->add_debug_info(self::DEBUG_GROUP, self::DEBUG_FIELD_TIMESTAMP, $cursor->get_fencepost_timestamp_ms());
                $derived_elem->add_debug_info(self::DEBUG_GROUP, self::DEBUG_FIELD_STRATEGY, 'head');
                $derived_elem->add_debug_info(self::DEBUG_GROUP, self::DEBUG_FIELD_OFFSET, $i);
                $derived_elems[] = $derived_elem;
            }
            $head_retrieve_count = count($derived_elems);
            if ($head_retrieve_count < $remain_count) {
                // need to keep going!
                return StreamResult::prepend($derived_elems, $this->enumerate_rec(
                    $remain_count - $head_retrieve_count,
                    $fencepost,
                    FencepostCursor::create_head(
                        $cursor->get_fencepost_timestamp_ms(),
                        $cursor->get_head_offset() + $head_retrieve_count,
                        $cursor->get_tail_cursor(),
                    ),
                    null,
                    $option
                ));
            } else {
                // we're done.
                return new StreamResult(false, $derived_elems);
            }
        }
    }

    /**
     * The final stage of fencepost enumeration: We have a tail cursor and no further fenceposts.
     * @param int $count The number of items to retrieve.
     * @param FencepostCursor $cursor The cursor.
     * @param StreamTracer|null $tracer The tracer/
     * @param EnumerationOptions|null $option The option on enumeration
     * @return StreamResult
     */
    protected function enumerate_final(
        int $count,
        FencepostCursor $cursor,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'enum', 'action' => 'final']);
        $tail_cursor = $cursor->get_tail_cursor();
        return $this->enumerate_final_inner($count, $tail_cursor, $cursor->get_fencepost_timestamp_ms(), $tracer, $option);
    }

    /**
     * The final stage of fencepost enumeration: We have a tail cursor and no further fenceposts.
     * @param int $count The number of items to retrieve.
     * @param StreamCursor $cursor The cursor.
     * @param int|null $fencepost_timestamp_ms The FencepostCursor timestamp
     * @param StreamTracer|null $tracer The tracer/
     * @param EnumerationOptions|null $option The option on enumeration
     * @return StreamResult
     */
    protected function enumerate_final_inner(
        int $count,
        ?StreamCursor $cursor,
        ?int $fencepost_timestamp_ms,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $inner_result = $this->inner->enumerate($count, $cursor, $tracer, $option);
        $derived_elems = [];
        foreach ($inner_result->get_elements() as $inner_elem) {
            $derived_elem = new DerivedStreamElement(
                $inner_elem,
                $this->get_identity(),
                FencepostCursor::create_final($inner_elem->get_cursor())
            );
            $derived_elem->add_debug_info(self::DEBUG_GROUP, self::DEBUG_FIELD_TIMESTAMP, $fencepost_timestamp_ms);
            $derived_elem->add_debug_info(self::DEBUG_GROUP, self::DEBUG_FIELD_STRATEGY, 'final_tail');
            $derived_elems[] = $derived_elem;
        }
        return new StreamResult($inner_result->is_exhaustive(), $derived_elems);
    }

    /**
     * Create and commit a new fencepost.
     * @param int $current_ms The timestamp of this fencepost.
     * @param int|null $previous_fencepost_ms The timestamp of the previous fencepost.
     * @param StreamTracer|null $tracer The tracer used during enumeration.
     * @param EnumerationOptions|null $option The option on enumeration
     * @return Fencepost|null
     */
    protected function commit_new_fencepost(
        int $current_ms,
        int $previous_fencepost_ms = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ) {
        // we are building a new fencepost between $current_ms and $previous_fencepost_ms.
        // step one: go get a new head.
        $after_ts_exclusive = $previous_fencepost_ms;
        if ($option && !is_null($option->get_enumerate_after_ts()) &&
            (is_null($previous_fencepost_ms) || $option->get_enumerate_after_ts() > $previous_fencepost_ms)) {
            $after_ts_exclusive = $option->get_enumerate_after_ts();
        }
        $head_unranked = $this->enumerate_inner_between($current_ms, $after_ts_exclusive, $this->head_count, null, $tracer);

        // Log new fence stats
        $this->log_commit_new_fence_stats($head_unranked, $current_ms, $after_ts_exclusive);

        if ($head_unranked->get_size() <= 0) {
            // got nothing. don't create an empty fencepost.
            return null;
        }

        $head_unranked_elems = $head_unranked->get_elements();
        $tail_cursor = $head_unranked->get_combined_cursor();

        if (is_null($previous_fencepost_ms) && (!($this->rank_seed))) {
            // step 2a: this is a seed fencepost and rank_seed is disabled - build an unranked seed fencepost!
            $fencepost = new Fencepost($head_unranked_elems, $tail_cursor, null);
        } else {
            // step 2b: materialize a ranked fencepost.
            $fencepost = new Fencepost($this->head_ranker->rank($head_unranked_elems, $tracer), $tail_cursor, $previous_fencepost_ms);
        }

        // step 3: persist the fencepost.
        $this->fencepost_provider->set_latest_fencepost($this->fence_id, $current_ms, $fencepost);
        return $fencepost;
    }

    /**
     * Enumerate the inner stream for a time range
     * @param int $before_ms_inclusive The inclusive "before" timestamp (i.e. the greater timestamp).
     * @param int|null $after_ms_exclusive The exclusive "after" timestamp (i.e. the lesser timestamp).
     * @param int $count How many items to return.
     * @param StreamCursor|null $inner_cursor The cursor to use to resume enumeration.
     * @param StreamTracer|null $tracer The tracer to use.
     * @throws \Exception When range filter filtered stream failed to enumerate.
     * @return StreamResult The result of enumeration on the inner stream.
     */
    protected function enumerate_inner_between(
        int $before_ms_inclusive,
        ?int $after_ms_exclusive,
        int $count,
        StreamCursor $inner_cursor = null,
        StreamTracer $tracer = null
    ): StreamResult {
        $option = new EnumerationOptions($before_ms_inclusive, $after_ms_exclusive);
        $result = $this->inner->enumerate($count, $inner_cursor, $tracer, $option);
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['inner'] = $this->inner->to_template();
        $base['head_ranker'] = $this->head_ranker->to_template();
        $base['head_count'] = $this->head_count;
        $base['rank_seed'] = $this->rank_seed;

        if ($this->timestamp_provider) {
            $base['timestamp_provider'] = $this->timestamp_provider->to_template();
        }
        return $base;
    }

    /**
     * @inheritDoc
     */
    public function can_enumerate_with_time_range(): bool
    {
        return true;
    }

    /**
     * @param StreamResult $unranked_candidates Stream Result for time-ranged inner stream enumeration
     * @param int $current_ms Currently timestamp in ms
     * @param int|null $previous_fencepost_ms Previous visit timestamp (for time_ranged candidate selection)
     * @return void
     */
    public function log_commit_new_fence_stats(
        StreamResult $unranked_candidates,
        int $current_ms,
        int $previous_fencepost_ms = null
    ): void {
        $ps_gap_in_seconds = ($current_ms - ($previous_fencepost_ms ?? 0)) / 1000;
        // Track when last_ts is missing, putting 1 second as a placeholder
        [$op, $gap] = $previous_fencepost_ms ? ['reload', $ps_gap_in_seconds] : ['unknown', 1];

        $log = StreamBuilder::getDependencyBag()->getLog();
        $log->histogramTick('dashboard_visit_gap', $op, $gap);

        // Only track candidate counts when reload interval is greater than 2 minutes or when last_visit is unknown
        if ($ps_gap_in_seconds > SECONDS_PER_MINUTE * 2) {
            $unranked = $unranked_candidates->get_elements();
            $post_cnt = count($unranked);
            // $post_cnt could be 0, add 0.01 for positive time duration range ticking in aggregate_tick
            StreamBuilder::getDependencyBag()->getLog()
                ->histogramTick('dashboard_candidates', 'rank', ($post_cnt + 0.01) / 1000);
            $blog_ids = array_map(function ($elem) {
                $origin = $elem->get_original_element();
                if ($origin instanceof PostStreamElementInterface) {
                    return $origin->getBlogId();
                } else {
                    return "NonPost";
                }
            }, $unranked);
            $uniq_blog_ids = array_count_values($blog_ids);
            try {
                $per_blog_count_string = Helpers::json_encode($uniq_blog_ids);
            } catch (\JsonException $e) {
                $per_blog_count_string = '';
                $log->exception($e, $this->get_identity());
            }
            $log->debug('dashboard_candidates', [
                $current_ms,
                $previous_fencepost_ms,
                is_null($previous_fencepost_ms) ? -1 : ($ps_gap_in_seconds / SECONDS_PER_MINUTE), // gap in minutes
                $post_cnt,
                count($uniq_blog_ids),
                $per_blog_count_string,
            ]);
        }
    }

    /**
     * @return int|null
     */
    protected function get_epoch(): ?int
    {
        return null;
    }
}
