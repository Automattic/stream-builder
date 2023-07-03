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
use Test\Tumblr\StreamBuilder\StreamElements\MockMaxStreamElement;
use Tumblr\StreamBuilder\FencepostRanking\Fencepost;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * Test for Fencepost
 */
class FencepostTest extends \PHPUnit\Framework\TestCase
{
    /** @var StreamElement[] */
    private array $head;
    /** @var StreamCursor */
    private $tail_cursor;
    /** @var int */
    private int $next_timestamp_ms;
    /** @var bool */
    private bool $is_inject_fence;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->head = [new MockMaxStreamElement(0, 'cool', new MockMaxCursor(1), 'amazing_id')];
        $this->tail_cursor = new MockMaxCursor(17);
        $this->next_timestamp_ms = 38383;
        $this->is_inject_fence = false;
    }

    /**
     * @return void
     */
    public function test_construct__empty_head()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Fencepost([], $this->tail_cursor, $this->next_timestamp_ms);
    }

    /**
     * @return void
     */
    public function test_construct__bad_head()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        new Fencepost([ new \stdClass() ], $this->tail_cursor, $this->next_timestamp_ms);
    }

    /**
     * @return Fencepost
     */
    public function test_construct()
    {
        $fp = new Fencepost($this->head, $this->tail_cursor, $this->next_timestamp_ms);
        $this->assertSame($this->head, $fp->get_head());
        $this->assertSame($this->tail_cursor, $fp->get_tail_cursor());
        $this->assertSame($this->next_timestamp_ms, $fp->get_next_timestamp_ms());
        $this->assertSame($this->is_inject_fence, $fp->is_inject_fence());
        $template = $fp->to_template();
        $this->assertSame([
            '_type' => Fencepost::class,
            'head' => [
                [
                    '_type' => MockMaxStreamElement::class,
                    'provider_id' => 'cool',
                    'cursor' => [
                        '_type' => MockMaxCursor::class,
                        'max' => 1,
                    ],
                    'element_id' => 'amazing_id',
                    'value' => 0,
                ],
            ],
            'tail_cursor' => [
                '_type' => MockMaxCursor::class,
                'max' => 17,
            ],
            'next_timestamp_ms' => 38383,
        ], $template);
        $this->assertSame(
            $fp->to_template(),
            StreamSerializer::from_template(new StreamContext($template, []))->to_template()
        );
        return $fp;
    }

    /**
     * @return Fencepost
     */
    public function test_construct_inject()
    {
        $fp = new Fencepost($this->head, $this->tail_cursor, $this->next_timestamp_ms, true);
        $this->assertSame($this->head, $fp->get_head());
        $this->assertSame($this->tail_cursor, $fp->get_tail_cursor());
        $this->assertSame($this->next_timestamp_ms, $fp->get_next_timestamp_ms());
        $this->assertTrue($fp->is_inject_fence());
        $template = $fp->to_template();
        $this->assertSame([
            '_type' => Fencepost::class,
            'head' => [
                [
                    '_type' => MockMaxStreamElement::class,
                    'provider_id' => 'cool',
                    'cursor' => [
                        '_type' => MockMaxCursor::class,
                        'max' => 1,
                    ],
                    'element_id' => 'amazing_id',
                    'value' => 0,
                ],
            ],
            'tail_cursor' => [
                '_type' => MockMaxCursor::class,
                'max' => 17,
            ],
            'next_timestamp_ms' => 38383,
            'is_inject' => true,
        ], $template);
        $this->assertSame(
            $fp->to_template(),
            StreamSerializer::from_template(new StreamContext($template, []))->to_template()
        );
        return $fp;
    }

    /**
     * Test to_string
     * @return void
     */
    public function test_to_string(): void
    {
        $fp = new Fencepost($this->head, $this->tail_cursor, $this->next_timestamp_ms, true);
        $this->assertSame(
            'amazing_id_1TEST_MockMaxCursor(17)',
            $fp->to_string()
        );
    }
}
