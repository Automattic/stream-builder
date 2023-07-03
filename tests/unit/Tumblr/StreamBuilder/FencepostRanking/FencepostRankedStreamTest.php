<?php declare(strict_types=1);

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

namespace Test\Tumblr\StreamBuilder\FencepostRanking;

use Test\Mock\Tumblr\StreamBuilder\Interfaces\TestContextProvider;
use Test\Tumblr\StreamBuilder\FixedSetTimestampProvider;
use Test\Tumblr\StreamBuilder\StreamCursors\MockMaxCursor;
use Test\Tumblr\StreamBuilder\StreamCursors\TestingChronoCursor;
use Test\Tumblr\StreamBuilder\StreamElements\TestingRankableChronoStreamElement;
use Test\Tumblr\StreamBuilder\StreamRankers\TestingRankableChronoStreamElementRanker;
use Test\Tumblr\StreamBuilder\Streams\TestingRankableChronoStream;
use Tests\Unit\Tumblr\StreamBuilder\StreamBuilderTest;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\FencepostRanking\Fencepost;
use Tumblr\StreamBuilder\FencepostRanking\FencepostCursor;
use Tumblr\StreamBuilder\FencepostRanking\FencepostRankedStream;
use Tumblr\StreamBuilder\FencepostRanking\TestingFencepostProvider;
use Tumblr\StreamBuilder\FencepostRanking\TestingFencepostRankedStream;
use Tumblr\StreamBuilder\Interfaces\Credentials;
use Tumblr\StreamBuilder\Interfaces\Log;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\TransientCacheProvider;

/**
 * Test for FencepostRankedStream
 */
class FencepostRankedStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|(Log&\PHPUnit\Framework\MockObject\MockObject)
     */
    private $log;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->log = $this->getMockBuilder(Log::class)->getMock();
        $creds = $this->getMockBuilder(Credentials::class)->getMock();
        $bag = new DependencyBag(
            $this->log,
            new TransientCacheProvider(),
            $creds,
            new TestContextProvider()
        );
        StreamBuilderTest::overrideStreamBuilderInit($bag);
    }

    /**
     * Redo the dependency bag injection.
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        StreamBuilderTest::resetStreamBuilder();
    }

    /**
     * Create a mock FencepostRankedStream
     * @param TestingRankableChronoStreamElement[] $inner_elements The inner stream from which items will be enumerated.
     * @param int $head_count How many pre-ranked element to cache.
     * @param bool $rank_seed If true, the seed (original) fencepost should be ranked,
     * otherwise it is a chronological epoch.
     * @param int|null $latest_fencepost_timestamp_ms The head fencepost id
     * @param int[] $next_timestamps The timestamp(s) to use for future fencepost creation.
     * @param Fencepost[] $fenceposts The fenceposts as timestamp => Fencepost mapping
     * @return \PHPUnit\Framework\MockObject\MockObject|FencepostRankedStream
     */
    private function create_fencepost_ranked_stream(
        array $inner_elements,
        int $head_count,
        bool $rank_seed,
        int $latest_fencepost_timestamp_ms = null,
        array $next_timestamps = [],
        array $fenceposts = []
    ) {
        return new TestingFencepostRankedStream(
            new TestingRankableChronoStream('test/inner', $inner_elements),
            new TestingRankableChronoStreamElementRanker('test/ranker'),
            $head_count,
            $rank_seed,
            'test/outer',
            new TestingFencepostProvider('test_fence_id', $latest_fencepost_timestamp_ms, $fenceposts),
            new FixedSetTimestampProvider($next_timestamps)
        );
    }

    /**
     * Do a single traversal check.
     * @param FencepostRankedStream $stream The stream being traversed
     * @param int $count How many items to enumerate
     * @param FencepostCursor|null $in_cursor The cursor to use
     * @param bool $should_exhaust If the stream should be exhausted.
     * @param StreamElement[] $expected_elements The expected elements
     * @param FencepostCursor|null $expected_cursor The expected combined cursor
     * @return FencepostCursor|null
     */
    private function check_traversal(
        FencepostRankedStream $stream,
        int $count,
        ?FencepostCursor $in_cursor,
        bool $should_exhaust,
        array $expected_elements,
        FencepostCursor $expected_cursor = null
    ) {
        $res = $stream->enumerate($count, $in_cursor);
        $this->assertEquals($should_exhaust, $res->is_exhaustive());
        $this->assertEquals($expected_elements, array_map(function (StreamElement $e) {
            return $e->get_original_element();
        }, $res->get_elements()));
        $cur = $res->get_combined_cursor();
        $this->assertEquals(
            $expected_cursor,
            $cur
        );
        $this->assertTrue(is_null($cur) || ($cur instanceof FencepostCursor));
        /** @var FencepostCursor|null $cur */
        return $cur;
    }

    /**
     * Test bad cursor
     * @return void
     */
    public function test_bad_cursor()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\InappropriateCursorException::class);
        $stream = $this->create_fencepost_ranked_stream([], 3, true, null, [10000], []);
        $stream->enumerate(10, new MockMaxCursor(32984));
    }

    /**
     * Test when:
     *  - There is no latest fencepost,
     *  - There is nothing to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__no_latest__empty()
    {
        $stream = $this->create_fencepost_ranked_stream([], 3, true, null, [10000], []);
        $this->check_traversal($stream, 10, null, true, [], null);
    }

    /**
     * Test when:
     *  - There is no latest fencepost,
     *  - There is more than enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__no_latest__nonempty__nonexhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 9999, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, null, [10000], []);
        $this->check_traversal($stream, 5, null, false, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is no latest fencepost,
     *  - There is not enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__no_latest__nonempty__exhaustive()
    {
        $this->log
            ->expects($this->once())
            ->method('histogramTick')
            ->with('dashboard_visit_gap', 'unknown', 1.0);
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, null, [10000], []);
        $this->check_traversal($stream, 10, null, true, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is a latest fencepost (in the past), but it is missing,
     *  - There is nothing to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__missing_past_latest__empty()
    {
        $stream = $this->create_fencepost_ranked_stream([], 3, true, 9000, [10000], []);
        $this->check_traversal($stream, 10, null, true, [], null);
    }

    /**
     * Test when:
     *  - There is a latest fencepost (in the past), but it is missing,
     *  - There is more than enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__missing_past_latest__nonempty__nonexhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 9999, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 9000, [10000], []);
        $this->check_traversal($stream, 5, null, false, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is a latest fencepost (in the past), but it is missing,
     *  - There is not enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__missing_past_latest__nonempty__exhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 9000, [10000], []);
        $this->check_traversal($stream, 10, null, true, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is a latest fencepost (in the future), but it is missing,
     *  - There is nothing to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__missing_future_latest__empty()
    {
        $stream = $this->create_fencepost_ranked_stream([], 3, true, 11000, [10000], []);
        $res = $stream->enumerate(10, null);
        $this->assertEquals(0, $res->get_size());
        $this->assertTrue($res->is_exhaustive());
    }

    /**
     * Test when:
     *  - There is a latest fencepost (in the future), but it is missing,
     *  - There is more than enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__missing_future_latest__nonempty__nonexhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 9999, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 11000, [10000], []);
        $this->check_traversal($stream, 5, null, false, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is a latest fencepost (in the future), but it is missing,
     *  - There is not enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__missing_future_latest__nonempty__exhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 11000, [10000], []);
        $this->check_traversal($stream, 10, null, true, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the past),
     *  - There is nothing to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_past_latest__empty()
    {
        $this->log
            ->expects($this->exactly(2))
            ->method('histogramTick')
            ->withConsecutive(
                ['dashboard_visit_gap', 'reload', 121.0],
                ['dashboard_candidates', 'rank', $this->anything()]
            );
        $this->log
            ->expects($this->once())
            ->method('debug')
            ->with('dashboard_candidates', [130000, 9000, 121 / 60, 0, 0, '[]']);
        $fencepost_elems_9000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 9000, 1);
        $present_fencepost_9000 = new Fencepost($fencepost_elems_9000, new TestingChronoCursor(8998));
        $stream = $this->create_fencepost_ranked_stream([], 3, true, 9000, [130000], [
            9000 => $present_fencepost_9000,
        ]);
        $this->check_traversal(
            $stream,
            10,
            null,
            true,
            $fencepost_elems_9000,
            FencepostCursor::create_head(9000, 3, new TestingChronoCursor(8998))
        );
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the past),
     *  - There is more than enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_past_latest__nonempty__nonexhaustive()
    {
        $this->log
            ->expects($this->exactly(2))
            ->method('histogramTick')
            ->withConsecutive(
                ['dashboard_visit_gap', 'reload', 121.0],
                ['dashboard_candidates', 'rank', $this->anything()]
            );
        $this->log
            ->expects($this->once())
            ->method('debug')
            ->with('dashboard_candidates', [130000, 9000, 121 / 60, 3, 1, '{"NonPost":3}']);
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 9999, 1);
        $fencepost_elems_9000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 9000, 1);
        $present_fencepost_9000 = new Fencepost($fencepost_elems_9000, new TestingChronoCursor(8998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 9000, [130000], [
            9000 => $present_fencepost_9000,
        ]);
        $this->check_traversal($stream, 5, null, false, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], FencepostCursor::create_tail(130000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the past),
     *  - There is not enough stuff to enumerate from inner, and
     *  - There is enough backfill from the fencepost, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_past_latest__nonempty__nonexhaustive__backfill()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(4, -1.0, 9999, 1);
        $fencepost_elems_9000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 9000, 1);
        $present_fencepost_9000 = new Fencepost($fencepost_elems_9000, new TestingChronoCursor(8998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 9000, [10000], [
            9000 => $present_fencepost_9000,
        ]);
        $this->check_traversal($stream, 6, null, false, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $fencepost_elems_9000[0],
            $fencepost_elems_9000[1],
        ], FencepostCursor::create_head(9000, 2, new TestingChronoCursor(8998)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the past),
     *  - There is not enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_past_latest__nonempty__exhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1);
        $fencepost_elems_9000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 9000, 1);
        $present_fencepost_9000 = new Fencepost($fencepost_elems_9000, new TestingChronoCursor(8998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 9000, [10000], [
            9000 => $present_fencepost_9000,
        ]);
        $this->check_traversal($stream, 10, null, true, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
            $fencepost_elems_9000[0],
            $fencepost_elems_9000[1],
            $fencepost_elems_9000[2],
        ], FencepostCursor::create_head(9000, 3, new TestingChronoCursor(8998)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the past),
     *  - There is not enough stuff to enumerate from inner, and
     *  - There is not enough backfill from the fencepost, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_past_latest__nonempty__exhaustive__backfill()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(4, -1.0, 9999, 1);
        $fencepost_elems_9000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 9000, 1);
        $present_fencepost_9000 = new Fencepost($fencepost_elems_9000, new TestingChronoCursor(8998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 9000, [10000], [
            9000 => $present_fencepost_9000,
        ]);
        $this->check_traversal($stream, 10, null, true, [
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[3],
            $fencepost_elems_9000[0],
            $fencepost_elems_9000[1],
            $fencepost_elems_9000[2],
        ], FencepostCursor::create_head(9000, 3, new TestingChronoCursor(8998)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the future),
     *  - There is nothing to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_future_latest__empty()
    {
        $fencepost_elems_11000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 11000, 1);
        $present_fencepost_11000 = new Fencepost($fencepost_elems_11000, new TestingChronoCursor(10998));
        $stream = $this->create_fencepost_ranked_stream([], 3, true, 11000, [10000], [
            11000 => $present_fencepost_11000,
        ]);
        $this->check_traversal(
            $stream,
            10,
            null,
            true,
            $fencepost_elems_11000,
            FencepostCursor::create_head(
                11000,
                3,
                new TestingChronoCursor(10998)
            )
        );
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the future),
     *  - There is more than enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_future_latest__nonempty__nonexhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 9999, 1);
        $fencepost_elems_11000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 11000, 1);
        $present_fencepost_11000 = new Fencepost($fencepost_elems_11000, new TestingChronoCursor(10998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 11000, [10000], [
            11000 => $present_fencepost_11000,
        ]);
        $this->check_traversal($stream, 5, null, false, array_merge(
            $fencepost_elems_11000,
            [
                $inner_stream_elems[0],
                $inner_stream_elems[1],
            ]
        ), FencepostCursor::create_tail(11000, new TestingChronoCursor(9998)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the future),
     *  - There is not enough stuff to enumerate from inner, and
     *  - There is enough backfill from the fencepost, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_future_latest__nonempty__nonexhaustive__backfill()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 9999, 1);
        $fencepost_elems_11000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 11000, 1);
        $present_fencepost_11000 = new Fencepost($fencepost_elems_11000, new TestingChronoCursor(10998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 11000, [10000], [
            11000 => $present_fencepost_11000,
        ]);
        $this->check_traversal($stream, 5, null, false, array_merge(
            $fencepost_elems_11000,
            [
                $inner_stream_elems[0],
                $inner_stream_elems[1],
            ]
        ), FencepostCursor::create_tail(11000, new TestingChronoCursor(9998)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the future),
     *  - There is not enough stuff to enumerate, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_future_latest__nonempty__exhaustive()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1);
        $fencepost_elems_11000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 11000, 1);
        $present_fencepost_11000 = new Fencepost($fencepost_elems_11000, new TestingChronoCursor(10998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 11000, [10000], [
            11000 => $present_fencepost_11000,
        ]);
        $this->check_traversal($stream, 10, null, true, array_merge(
            $fencepost_elems_11000,
            $inner_stream_elems
        ), FencepostCursor::create_tail(11000, new TestingChronoCursor(9995)));
    }

    /**
     * Test when:
     *  - There is a present latest fencepost (in the future),
     *  - There is not enough stuff to enumerate from inner, and
     *  - There is not enough backfill from the fencepost, and
     *  - We are asking for the first page.
     * @return void
     */
    public function test_enumerate__no_cursor__present_future_latest__nonempty__exhaustive__backfill()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 9999, 1);
        $fencepost_elems_11000 = TestingRankableChronoStreamElement::create_sequence(3, 1, 11000, 1);
        $present_fencepost_11000 = new Fencepost($fencepost_elems_11000, new TestingChronoCursor(10998));
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 11000, [10000], [
            11000 => $present_fencepost_11000,
        ]);
        $this->check_traversal($stream, 10, null, true, array_merge(
            $fencepost_elems_11000,
            $inner_stream_elems
        ), FencepostCursor::create_tail(11000, new TestingChronoCursor(9997)));
    }

    /**
     * Test complex traversal, no cursor. where the leading part fits into the head.
     * @return void
     */
    public function test_traversal__no_cursor__leading_fits_into_head()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
            7000 => new Fencepost(array_reverse($elems_6k), new TestingChronoCursor(6000), 5000),
        ];
        $stream = $this->create_fencepost_ranked_stream($elems_all, 2, true, 9000, [10000], $fenceposts);
        $this->check_traversal($stream, 11, null, false, array_merge(
            array_reverse($elems_9k),
            array_reverse($elems_8k),
            $elems_7k,
            array_reverse($elems_6k),
            array_slice($elems_5k, 0, 3)
        ), FencepostCursor::create_tail(7000, new TestingChronoCursor(5997)));
    }

    /**
     * Test complex traversal, no cursor. where the leading part leaves a tail.
     * @return void
     */
    public function test_traversal__no_cursor__leading_has_tail()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
            7000 => new Fencepost(array_reverse($elems_6k), new TestingChronoCursor(6000), 5000),
        ];
        $stream = $this->create_fencepost_ranked_stream($elems_all, 2, true, 9000, [10000], $fenceposts);
        $this->check_traversal($stream, 14, null, false, array_merge(
            array_reverse(array_slice($elems_9k, 0, 2)),
            array_slice($elems_9k, 2, 3),
            array_reverse($elems_8k),
            $elems_7k,
            array_reverse($elems_6k),
            array_slice($elems_5k, 0, 3)
        ), FencepostCursor::create_tail(7000, new TestingChronoCursor(5997)));
    }

    /**
     * Test complex segmented 1-traversal.
     * @return void
     */
    public function test_traversal__segmented_1()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(1, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
            7000 => new Fencepost(array_reverse($elems_6k), new TestingChronoCursor(6000), 5000),
        ];
        $stream = $this->create_fencepost_ranked_stream($elems_all, 2, true, 9000, range(10000, 20000, 500), $fenceposts);

        // @codingStandardsIgnoreStart
        $cur = null;
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_9k[1] ], FencepostCursor::create_head(10000, 1, new TestingChronoCursor(9998)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_9k[0] ], FencepostCursor::create_head(10000, 2, new TestingChronoCursor(9998)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_9k[2] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9997)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_9k[3] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9996)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_9k[4] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_8k[1] ], FencepostCursor::create_head(9000, 1, new TestingChronoCursor(8000)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_8k[0] ], FencepostCursor::create_head(9000, 2, new TestingChronoCursor(8000)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_7k[0] ], FencepostCursor::create_tail(9000, new TestingChronoCursor(7999)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_7k[1] ], FencepostCursor::create_tail(9000, new TestingChronoCursor(7998)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_6k[0] ], FencepostCursor::create_head(7000, 1, new TestingChronoCursor(6000)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_5k[0] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5999)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_5k[1] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5998)));
        $cur = $this->check_traversal($stream, 1, $cur, false, [ $elems_5k[2] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5997)));
        $cur = $this->check_traversal($stream, 1, $cur, true, [], null);
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test complex segmented 2-traversal.
     * @return void
     */
    public function test_traversal__segmented_2()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(1, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
            7000 => new Fencepost(array_reverse($elems_6k), new TestingChronoCursor(6000), 5000),
        ];
        $stream = $this->create_fencepost_ranked_stream($elems_all, 2, true, 9000, range(10000, 20000, 500), $fenceposts);

        // @codingStandardsIgnoreStart
        $cur = null;
        $cur = $this->check_traversal($stream, 2, $cur, false, [ $elems_9k[1], $elems_9k[0] ], FencepostCursor::create_head(10000, 2, new TestingChronoCursor(9998)));
        $cur = $this->check_traversal($stream, 2, $cur, false, [ $elems_9k[2], $elems_9k[3] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9996)));
        $cur = $this->check_traversal($stream, 2, $cur, false, [ $elems_9k[4], $elems_8k[1] ], FencepostCursor::create_head(9000, 1, new TestingChronoCursor(8000)));
        $cur = $this->check_traversal($stream, 2, $cur, false, [ $elems_8k[0], $elems_7k[0] ], FencepostCursor::create_tail(9000, new TestingChronoCursor(7999)));
        $cur = $this->check_traversal($stream, 2, $cur, false, [ $elems_7k[1], $elems_6k[0] ], FencepostCursor::create_head(7000, 1, new TestingChronoCursor(6000)));
        $cur = $this->check_traversal($stream, 2, $cur, false, [ $elems_5k[0], $elems_5k[1] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5998)));
        $cur = $this->check_traversal($stream, 2, $cur, true, [ $elems_5k[2] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5997)));
        $cur = $this->check_traversal($stream, 2, $cur, true, [], null);
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test complex segmented 3-traversal.
     * @return void
     */
    public function test_traversal__segmented_3()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(1, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
            7000 => new Fencepost(array_reverse($elems_6k), new TestingChronoCursor(6000), 5000),
        ];
        $stream = $this->create_fencepost_ranked_stream($elems_all, 2, true, 9000, range(10000, 20000, 500), $fenceposts);

        // @codingStandardsIgnoreStart
        $cur = null;
        $cur = $this->check_traversal($stream, 3, $cur, false, [ $elems_9k[1], $elems_9k[0], $elems_9k[2] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9997)));
        $cur = $this->check_traversal($stream, 3, $cur, false, [ $elems_9k[3], $elems_9k[4], $elems_8k[1] ], FencepostCursor::create_head(9000, 1, new TestingChronoCursor(8000)));
        $cur = $this->check_traversal($stream, 3, $cur, false, [ $elems_8k[0], $elems_7k[0], $elems_7k[1] ], FencepostCursor::create_tail(9000, new TestingChronoCursor(7998)));
        $cur = $this->check_traversal($stream, 3, $cur, false, [ $elems_6k[0], $elems_5k[0], $elems_5k[1] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5998)));
        $cur = $this->check_traversal($stream, 3, $cur, true, [ $elems_5k[2] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5997)));
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test complex segmented 4-traversal.
     * @return void
     */
    public function test_traversal__segmented_4()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(1, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
            7000 => new Fencepost(array_reverse($elems_6k), new TestingChronoCursor(6000), 5000),
        ];
        $stream = $this->create_fencepost_ranked_stream($elems_all, 2, true, 9000, range(10000, 20000, 500), $fenceposts);

        // @codingStandardsIgnoreStart
        $cur = null;
        $cur = $this->check_traversal($stream, 4, $cur, false, [ $elems_9k[1], $elems_9k[0], $elems_9k[2], $elems_9k[3] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9996)));
        $cur = $this->check_traversal($stream, 4, $cur, false, [ $elems_9k[4], $elems_8k[1], $elems_8k[0], $elems_7k[0] ], FencepostCursor::create_tail(9000, new TestingChronoCursor(7999)));
        $cur = $this->check_traversal($stream, 4, $cur, false, [ $elems_7k[1], $elems_6k[0], $elems_5k[0], $elems_5k[1] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5998)));
        $cur = $this->check_traversal($stream, 4, $cur, true, [ $elems_5k[2] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5997)));
        $cur = $this->check_traversal($stream, 4, $cur, true, [], null);
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test complex segmented 5-traversal.
     * @return void
     */
    public function test_traversal__segmented_5()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(1, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
            7000 => new Fencepost(array_reverse($elems_6k), new TestingChronoCursor(6000), 5000),
        ];
        $stream = $this->create_fencepost_ranked_stream($elems_all, 2, true, 9000, range(10000, 20000, 500), $fenceposts);

        // @codingStandardsIgnoreStart
        $cur = null;
        $cur = $this->check_traversal($stream, 5, $cur, false, [ $elems_9k[1], $elems_9k[0], $elems_9k[2], $elems_9k[3], $elems_9k[4] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
        $cur = $this->check_traversal($stream, 5, $cur, false, [ $elems_8k[1], $elems_8k[0], $elems_7k[0], $elems_7k[1], $elems_6k[0] ], FencepostCursor::create_head(7000, 1, new TestingChronoCursor(6000)));
        $cur = $this->check_traversal($stream, 5, $cur, true, [ $elems_5k[0], $elems_5k[1], $elems_5k[2] ], FencepostCursor::create_tail(7000, new TestingChronoCursor(5997)));
        $cur = $this->check_traversal($stream, 5, $cur, true, [], null);
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test complex traversal when fencepost cache eviction happens in the middle.
     * @return void
     */
    public function test_traversal__fencepost_cache_eviction_middle()
    {
        $elems_all = array_merge(
            $elems_9k = TestingRankableChronoStreamElement::create_sequence(5, -1.0, 9999, 1),
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(1, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 5999, 1)
        );
        $fenceposts = [
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
        ];
        $stream = $this->create_fencepost_ranked_stream(
            $elems_all,
            2,
            true,
            9000,
            range(10000, 20000, 500),
            $fenceposts
        );

        // @codingStandardsIgnoreStart
        $cur = null;
        $cur = $this->check_traversal($stream, 5, $cur, false, [ $elems_9k[1], $elems_9k[0], $elems_9k[2], $elems_9k[3], $elems_9k[4] ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9995)));
        $cur = $this->check_traversal($stream, 5, $cur, false, [ $elems_8k[1], $elems_8k[0], $elems_6k[0], $elems_5k[0], $elems_5k[1]], FencepostCursor::create_final(new TestingChronoCursor(5998)));
        $cur = $this->check_traversal($stream, 5, $cur, true, [ $elems_5k[2] ], FencepostCursor::create_final(new TestingChronoCursor(5997)));
        $this->check_traversal($stream, 5, $cur, true, [], null);
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test complex traversal when fencepost cache eviction happens in the head.
     * @return void
     */
    public function test_traversal__fencepost_cache_eviction_head()
    {
        $elems_all = array_merge(
            $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1),
            $elems_7k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 7999, 1),
            $elems_6k = TestingRankableChronoStreamElement::create_sequence(1, -1.0, 6999, 1),
            $elems_5k = TestingRankableChronoStreamElement::create_sequence(3, -1.0, 5999, 1)
        );
        $fenceposts = [];
        $stream = $this->create_fencepost_ranked_stream(
            $elems_all,
            2,
            true,
            9000,
            range(10000, 20000, 500),
            $fenceposts
        );

        // @codingStandardsIgnoreStart
        $cur = null;
        $cur = $this->check_traversal($stream, 5, $cur, false, [ $elems_8k[1], $elems_8k[0], $elems_7k[0], $elems_7k[1], $elems_6k[0]], FencepostCursor::create_tail(9000, new TestingChronoCursor(6999)));
        $cur = $this->check_traversal($stream, 5, $cur, true, [ $elems_5k[0], $elems_5k[1], $elems_5k[2] ], FencepostCursor::create_final(new TestingChronoCursor(5997)));
        $this->check_traversal($stream, 5, $cur, true, [], null);
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test dangling cursor.
     * @return void
     */
    public function test_dangling_cursor()
    {
        $elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 5000, 1);
        $stream = $this->create_fencepost_ranked_stream($elems, 2, true, 9000, [10000], []);

        $cur_5001 = new TestingChronoCursor(5001);
        $cur_5000 = new TestingChronoCursor(5000);
        $cur_4996 = new TestingChronoCursor(4996);
        $cur_4995 = new TestingChronoCursor(4995);
        $cur_4991 = new TestingChronoCursor(4991);

        $slice_0_5 = array_slice($elems, 0, 5);
        $slice_1_5 = array_slice($elems, 1, 5);
        $slice_6_4 = array_slice($elems, 6, 4);

        // @codingStandardsIgnoreStart
        $this->check_traversal($stream, 5, FencepostCursor::create_head(9000, 0, $cur_5001), false, $slice_0_5, FencepostCursor::create_final($cur_4996));
        $this->check_traversal($stream, 5, FencepostCursor::create_head(9000, 0, $cur_5000), false, $slice_1_5, FencepostCursor::create_final($cur_4995));
        $this->check_traversal($stream, 5, FencepostCursor::create_head(9000, 0, $cur_4995), true, $slice_6_4, FencepostCursor::create_final($cur_4991));
        $this->check_traversal($stream, 5, FencepostCursor::create_head(9000, 100, $cur_5001), false, $slice_0_5, FencepostCursor::create_final($cur_4996));
        $this->check_traversal($stream, 5, FencepostCursor::create_head(9000, 100, $cur_5000), false, $slice_1_5, FencepostCursor::create_final($cur_4995));
        $this->check_traversal($stream, 5, FencepostCursor::create_head(9000, 100, $cur_4995), true, $slice_6_4, FencepostCursor::create_final($cur_4991));
        $this->check_traversal($stream, 5, FencepostCursor::create_tail(9000, $cur_5001), false, $slice_0_5, FencepostCursor::create_final($cur_4996));
        $this->check_traversal($stream, 5, FencepostCursor::create_tail(9000, $cur_5000), false, $slice_1_5, FencepostCursor::create_final($cur_4995));
        $this->check_traversal($stream, 5, FencepostCursor::create_tail(9000, $cur_4995), true, $slice_6_4, FencepostCursor::create_final($cur_4991));
        $this->check_traversal($stream, 5, FencepostCursor::create_final($cur_5001), false, $slice_0_5, FencepostCursor::create_final($cur_4996));
        $this->check_traversal($stream, 5, FencepostCursor::create_final($cur_5000), false, $slice_1_5, FencepostCursor::create_final($cur_4995));
        $this->check_traversal($stream, 5, FencepostCursor::create_final($cur_4995), true, $slice_6_4, FencepostCursor::create_final($cur_4991));
        // @codingStandardsIgnoreEnd
    }

    /**
     * Test when stuff returned from the inner stream is newer than the timestamp of the fencepost being built
     * @return void
     */
    public function test_future_stream_head()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 10001, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 3, true, 9000, [10000], []);
        $this->check_traversal($stream, 5, null, false, [
            $inner_stream_elems[3],
            $inner_stream_elems[2],
            $inner_stream_elems[1],
            $inner_stream_elems[4],
            $inner_stream_elems[5],
        ], FencepostCursor::create_tail(10000, new TestingChronoCursor(9996)));
    }

    /**
     * Test that unranked seed mode works, and indeed does not rank.
     * (no previous fencepost)
     * @return void
     */
    public function test_unranked_seed__first_fencepost()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 1000, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 2, false, null, [1005]);
        $cur_tail_996 = FencepostCursor::create_tail(1005, new TestingChronoCursor(996));
        $this->check_traversal($stream, 5, null, false, array_slice($inner_stream_elems, 0, 5), $cur_tail_996);
        $this->assertEquals(
            new Fencepost(array_slice($inner_stream_elems, 0, 2), new TestingChronoCursor(999), null),
            $stream->get_fencepost_provider()->get_fencepost($stream->get_fence_id(), 1005)
        );
    }

    /**
     * Test that unranked seed mode DOES rank if previous fencepost is missing.
     * (missing previous fencepost)
     * @return void
     */
    public function test_unranked_seed__missing_fencepost()
    {
        $inner_stream_elems = TestingRankableChronoStreamElement::create_sequence(10, -1.0, 1000, 1);
        $stream = $this->create_fencepost_ranked_stream($inner_stream_elems, 2, false, 700, [1005]);
        $cur_tail_996 = FencepostCursor::create_tail(1005, new TestingChronoCursor(996));
        $this->check_traversal($stream, 5, null, false, [
            $inner_stream_elems[1],
            $inner_stream_elems[0],
            $inner_stream_elems[2],
            $inner_stream_elems[3],
            $inner_stream_elems[4],
        ], $cur_tail_996);
        $this->assertEquals(
            new Fencepost(array_reverse(array_slice($inner_stream_elems, 0, 2)), new TestingChronoCursor(999), 700),
            $stream->get_fencepost_provider()->get_fencepost($stream->get_fence_id(), 1005)
        );
    }

    /**
     * Test enumerate_rec_inject returns empty
     * @return void
     */
    public function test_enumerate_rec_inject()
    {
        $elems_inject = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 99, 1);
        $elems_8k = TestingRankableChronoStreamElement::create_sequence(2, -1.0, 8999, 1);

        $fenceposts = [
            230000 => new Fencepost($elems_inject, new TestingChronoCursor(97), 110000, true),
            9000 => new Fencepost(array_reverse($elems_8k), new TestingChronoCursor(8000), 7000),
        ];
        $stream = $this->create_fencepost_ranked_stream([], 2, true, 230000, [240000], $fenceposts);
        $this->check_traversal($stream, 1, null, true, [], null);
    }
}
