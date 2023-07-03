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

use Test\Tumblr\StreamBuilder\StreamCursors\MockMaxCursor;
use Tumblr\StreamBuilder\FencepostRanking\FencepostCursor;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\MaxIntCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * Test for FencepostCursor
 */
class FencepostCursorTest extends \PHPUnit\Framework\TestCase
{
    /** @var int */
    private $fencepost_timestamp_ms;
    /** @var StreamCursor */
    private $tail_cursor;
    /** @var StreamCursor|null */
    private $inject_cursor;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->fencepost_timestamp_ms = 11223344;
        $this->tail_cursor = new MockMaxCursor(17);
        $this->inject_cursor = new MaxIntCursor(100);
    }

    /**
     * @return void
     */
    public function test_create_head__negative_timestamp()
    {
        $this->expectException(\InvalidArgumentException::class);
        FencepostCursor::create_head(-23987, 0, $this->tail_cursor);
    }

    /**
     * @return void
     */
    public function test_create_inject__negative_timestamp()
    {
        $this->expectException(\InvalidArgumentException::class);
        FencepostCursor::create_inject(-23987, $this->tail_cursor, $this->inject_cursor);
    }

    /**
     * @return void
     */
    public function test_create_head__negative_offset()
    {
        $this->expectException(\InvalidArgumentException::class);
        FencepostCursor::create_head($this->fencepost_timestamp_ms, -123, $this->tail_cursor);
    }

    /**
     * @return void
     */
    public function test_head()
    {
        $fpc = FencepostCursor::create_head($this->fencepost_timestamp_ms, 17, $this->tail_cursor);
        $this->assertEquals($this->fencepost_timestamp_ms, $fpc->get_fencepost_timestamp_ms());
        $this->assertEquals(FencepostCursor::REGION_HEAD, $fpc->get_region());
        $this->assertEquals(17, $fpc->get_head_offset());
        $this->assertEquals($this->tail_cursor, $fpc->get_tail_cursor());
        $this->assertEquals('FencepostCursor(11223344,HEAD,17,TEST_MockMaxCursor(17))', (string) $fpc);
        $template = $fpc->to_template();
        $this->assertEquals([
            '_type' => FencepostCursor::class,
            'f' => 11223344,
            'r' => 10,
            't' => [
                '_type' => MockMaxCursor::class,
                'max' => 17,
            ],
            'h' => 17,
        ], $template);
        $this->assertEquals($fpc, StreamSerializer::from_template(new StreamContext($template, [])));
    }


    /**
     * @return void
     */
    public function test_create_tail__negative_timestamp()
    {
        $this->expectException(\InvalidArgumentException::class);
        FencepostCursor::create_tail(-12345, $this->tail_cursor);
    }

    /**
     * @return void
     */
    public function test_tail()
    {
        $fpc = FencepostCursor::create_tail($this->fencepost_timestamp_ms, $this->tail_cursor);
        $this->assertEquals($this->fencepost_timestamp_ms, $fpc->get_fencepost_timestamp_ms());
        $this->assertEquals(FencepostCursor::REGION_TAIL, $fpc->get_region());
        $this->assertEquals($this->tail_cursor, $fpc->get_tail_cursor());
        $this->assertEquals('FencepostCursor(11223344,TAIL,-1,TEST_MockMaxCursor(17))', (string) $fpc);
        $template = $fpc->to_template();
        $this->assertEquals([
            '_type' => FencepostCursor::class,
            'f' => 11223344,
            'r' => 20,
            't' => [
                '_type' => MockMaxCursor::class,
                'max' => 17,
            ],
        ], $template);
        $this->assertEquals($fpc, StreamSerializer::from_template(new StreamContext($template, [])));
    }

    /**
     * @return void
     */
    public function test_get_tail_head_offset()
    {
        $this->expectException(\LogicException::class);
        $fpc = FencepostCursor::create_tail($this->fencepost_timestamp_ms, $this->tail_cursor);
        $fpc->get_head_offset();
    }

    /**
     * Test creating inject cursor
     * @return void
     */
    public function test_inject()
    {
        $fpc = FencepostCursor::create_inject($this->fencepost_timestamp_ms, $this->tail_cursor, $this->inject_cursor);
        $this->assertEquals($this->fencepost_timestamp_ms, $fpc->get_fencepost_timestamp_ms());
        $this->assertEquals(FencepostCursor::REGION_INJECT, $fpc->get_region());
        $this->assertEquals($this->tail_cursor, $fpc->get_tail_cursor());
        $this->assertEquals($this->inject_cursor, $fpc->get_inject_cursor());
        $this->assertEquals('FencepostCursor(11223344,INJECT,-1,TEST_MockMaxCursor(17)MaxInt(100))', (string) $fpc);
        $template = $fpc->to_template();
        $this->assertEquals([
            '_type' => FencepostCursor::class,
            'f' => 11223344,
            'r' => 30,
            't' => [
                '_type' => MockMaxCursor::class,
                'max' => 17,
            ],
            'i' => [
                '_type' => MaxIntCursor::class,
                'v' => 100,
            ],
        ], $template);
        $this->assertEquals($fpc, StreamSerializer::from_template(new StreamContext($template, [])));
    }

    /**
     * @return void
     */
    public function test_final()
    {
        $fpc = FencepostCursor::create_final($this->tail_cursor);
        $this->assertEquals(-1, $fpc->get_fencepost_timestamp_ms());
        $this->assertEquals(FencepostCursor::REGION_TAIL, $fpc->get_region());
        $this->assertEquals($this->tail_cursor, $fpc->get_tail_cursor());
        $this->assertEquals('FencepostCursor(-1,TAIL,-1,TEST_MockMaxCursor(17))', (string) $fpc);
        $template = $fpc->to_template();
        $this->assertEquals([
            '_type' => FencepostCursor::class,
            'f' => -1,
            'r' => 20,
            't' => [
                '_type' => MockMaxCursor::class,
                'max' => 17,
            ],
        ], $template);
        $this->assertEquals($fpc, StreamSerializer::from_template(new StreamContext($template, [])));
    }

    /**
     * @return void
     */
    public function test_get_final_head_offset()
    {
        $this->expectException(\LogicException::class);
        $fpc = FencepostCursor::create_final($this->tail_cursor);
        $fpc->get_head_offset();
    }

    /**
     * @return void
     */
    public function test_combine__illegal()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\UncombinableCursorException::class);
        $fpc = FencepostCursor::create_final($this->tail_cursor);
        $fpc->combine_with(new MockMaxCursor(17));
    }

    /**
     * @return void
     */
    public function test_combine()
    {
        // For inject with no cursor, it is relying on inner stream applying impression filtering for candidate enumeration
        $inject_no_cursor_1 = FencepostCursor::create_inject(350, null, null);
        $inject_no_cursor_2 = FencepostCursor::create_inject(350, null, null);
        $inject_early = FencepostCursor::create_inject(300, new MockMaxCursor(0), new MaxIntCursor(5));
        $inject_late = FencepostCursor::create_inject(250, new MockMaxCursor(0), new MaxIntCursor(2));
        $early_head_early = FencepostCursor::create_head(200, 10, new MockMaxCursor(0));
        $early_head_late = FencepostCursor::create_head(200, 20, new MockMaxCursor(0));
        $early_tail_early = FencepostCursor::create_tail(200, new MockMaxCursor(10));
        $early_tail_late = FencepostCursor::create_tail(200, new MockMaxCursor(20));
        $late_head_early = FencepostCursor::create_head(100, 10, new MockMaxCursor(0));
        $late_head_late = FencepostCursor::create_head(100, 20, new MockMaxCursor(0));
        $late_tail_early = FencepostCursor::create_tail(100, new MockMaxCursor(10));
        $late_tail_late = FencepostCursor::create_tail(100, new MockMaxCursor(20));
        $final_early = FencepostCursor::create_final(new MockMaxCursor(100));
        $final_late = FencepostCursor::create_final(new MockMaxCursor(200));

        /** @var FencepostCursor[] $ord */
        $ord = [
            $inject_no_cursor_1,
            $inject_no_cursor_2,
            $inject_early,
            $inject_late,
            $early_head_early,
            $early_head_late,
            $early_tail_early,
            $early_tail_late,
            $late_head_early,
            $late_head_late,
            $late_tail_early,
            $late_tail_late,
            $final_early,
            $final_late,
        ];

        // exhaustively validate all partial orderings:
        for ($i = 0; $i < count($ord); $i++) {
            $ci = $ord[$i];
            for ($j = 0; $j < count($ord); $j++) {
                $cj = $ord[$j];
                if ($i < $j) {
                    $this->assertEquals($cj, $ci->combine_with($cj));
                    $this->assertEquals($cj, $cj->combine_with($ci));
                } elseif ($i > $j) {
                    $this->assertEquals($ci, $ci->combine_with($cj));
                    $this->assertEquals($ci, $cj->combine_with($ci));
                }
            }
        }
    }
}
