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

namespace Test\Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\SignalFetchers\CompositeSignalFetcher;
use Tumblr\StreamBuilder\SignalFetchers\SignalBundle;
use Tumblr\StreamBuilder\SignalFetchers\SignalFetcher;
use Tumblr\StreamBuilder\SignalFetchers\TimestampFetcher;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Tests for CompositeSignalFetcher
 */
class CompositeSignalFetcherTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the fetch() method works.
     * @return void
     */
    public function test_fetch()
    {
        /** @var StreamElement $e1 */
        $e1 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $e2 */
        $e2 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $inner1 = $this->getMockBuilder(SignalFetcher::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $inner1->expects($this->exactly(1))->method('fetch_inner')->willReturn(new SignalBundle([
            Helpers::memory_element_id($e1) => [ 's1' => 3 ],
            Helpers::memory_element_id($e2) => [ 's1' => 4 ],
        ]));

        $inner2 = $this->getMockBuilder(SignalFetcher::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $inner2->expects($this->exactly(1))->method('fetch_inner')->willReturn(new SignalBundle([
            Helpers::memory_element_id($e1) => [ 's2' => 'foo' ],
            Helpers::memory_element_id($e2) => [ 's2' => 'bar' ],
        ]));

        $composite = new CompositeSignalFetcher([ $inner1, $inner2 ], 'cool');

        $sb = $composite->fetch([]);

        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e1) => [ 's1' => 3, 's2' => 'foo' ],
            Helpers::memory_element_id($e2) => [ 's1' => 4, 's2' => 'bar' ],
        ]), $sb);
    }

    /**
     * Test the from_template method.
     * @return CompositeSignalFetcher
     */
    public function test_from_template()
    {
        $template = [
            '_type' => CompositeSignalFetcher::class,
            'fetchers' => [
                [ '_type' => TimestampFetcher::class ],
                [ '_type' => TimestampFetcher::class ],
            ],
        ];
        $sc = new StreamContext($template, [], null, 'test_root');
        $sf = CompositeSignalFetcher::from_template($sc);
        $this->assertEquals(new CompositeSignalFetcher([
            new TimestampFetcher('test_root/fetchers/0'),
            new TimestampFetcher('test_root/fetchers/1'),
        ], 'test_root'), $sf);
        return $sf;
    }

    /**
     * Test the to_template method.
     * @depends test_from_template
     * @param CompositeSignalFetcher $sf The CompositeSignalFetcher
     * @return void
     */
    public function test_to_template(CompositeSignalFetcher $sf)
    {
        $this->assertEquals([
            '_type' => CompositeSignalFetcher::class,
            'fetchers' => [
                [ '_type' => TimestampFetcher::class ],
                [ '_type' => TimestampFetcher::class ],
            ],
        ], $sf->to_template());
    }
}
