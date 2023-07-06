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

namespace Test\Tumblr\StreamBuilder\StreamCursors;

use Test\Tumblr\StreamBuilder\StreamElements\MockMaxStreamElement;
use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Exceptions\UncombinableCursorException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\BufferedCursor;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\TransientCacheProvider;
use function is_null;

/**
 * Tests for BufferedCursor
 */
class BufferedCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Method to build a mock element.
     * @param string $provider_identity The identity of the element.
     * @param StreamCursor|null $cursor The cursor in the element.
     * @return StreamElement
     */
    private function make_mock_element(string $provider_identity, StreamCursor $cursor = null): StreamElement
    {
        $m = $this->getMockBuilder(StreamElement::class)
            ->setConstructorArgs([ $provider_identity, $cursor ])
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $m->expects($this->any())->method('to_template')->willReturn([
            '_type' => 'mock',
            'id' => $provider_identity,
            'cur' => (is_null($cursor) ? null : $cursor->to_template()),
        ]);
        return $m;
    }

    /**
     * Test combining cursors
     * @return void
     */
    public function test_combine()
    {
        $e1 = $this->make_mock_element('p1');
        $e2 = $this->make_mock_element('p2');
        $e3 = $this->make_mock_element('p3');
        $e4 = $this->make_mock_element('p4');

        $cp = new TransientCacheProvider();

        $bc1 = new BufferedCursor(null, [], $cp);
        $bc2 = new BufferedCursor(null, [ $e1 ], $cp);
        $bc3 = new BufferedCursor(null, [ $e1, $e2 ], $cp);
        $bc4 = new BufferedCursor(null, [ $e3, $e1 ], $cp);
        $bc5 = new BufferedCursor(null, [ $e1, $e2, $e3 ], $cp);
        $bc6 = new BufferedCursor(null, [ $e4, $e3, $e2 ], $cp);

        $this->assertEquals(new BufferedCursor(null, [], $cp), $bc1->combine_with($bc2));
        $this->assertEquals(new BufferedCursor(null, [], $cp), $bc2->combine_with($bc1));
        $this->assertEquals(new BufferedCursor(null, [ $e1 ], $cp), $bc3->combine_with($bc4));
        $this->assertEquals(new BufferedCursor(null, [ $e1 ], $cp), $bc4->combine_with($bc3));
        $this->assertEquals(new BufferedCursor(null, [ $e2, $e3 ], $cp), $bc5->combine_with($bc6));
        $this->assertEquals(new BufferedCursor(null, [ $e3, $e2 ], $cp), $bc6->combine_with($bc5));
    }

    /**
     * Test can not combine cursor
     */
    public function test_can_not_combine()
    {
        $this->expectException(UncombinableCursorException::class);
        $bc = new BufferedCursor(null, [], new TransientCacheProvider());
        $bc->combine_with(new MultiCursor([]));
    }

    /**
     * Test to_template
     * @return void
     */
    public function test_to_template()
    {
        $cp = new TransientCacheProvider();

        $bc = new BufferedCursor(
            new BufferedCursor(null, [
                $this->make_mock_element('inner_1', new BufferedCursor(null, [
                    $this->make_mock_element('inner_1.1', null),
                ], $cp)),
                $this->make_mock_element('inner_2', null),
            ], $cp),
            [
                $this->make_mock_element('outer_1', new BufferedCursor(null, [], $cp)),
                $this->make_mock_element('outer_2', null),
            ],
            $cp
        );

        $this->assertEquals([
            '_type' => BufferedCursor::class,
            'ic' => [ '_type' => BufferedCursor::class, 'b' => 'bufcur_9dfe0b17d3c5a179cdd83a906ba2e5ae' ],
            'b' => 'bufcur_f2d633251c9980590abce96f7c3af97a',
        ], $bc->to_template());

        $this->assertEquals([
            [
                '_type' => 'mock',
                'id' => 'inner_1',
                'cur' => [ '_type' => 'Tumblr\\StreamBuilder\\StreamCursors\\BufferedCursor', 'b' => 'bufcur_48a575150d9f796896bb6799cee8b2c3' ],
            ],
            [
                '_type' => 'mock',
                'id' => 'inner_2',
                'cur' => null,
            ],
        ], Helpers::json_decode($cp->get(CacheProvider::OBJECT_TYPE_BUFFERED_STREAM_ELEMENTS, 'bufcur_9dfe0b17d3c5a179cdd83a906ba2e5ae')));

        $this->assertEquals([
            [
                '_type' => 'mock',
                'id' => 'outer_1',
                'cur' => [ '_type' => 'Tumblr\\StreamBuilder\\StreamCursors\\BufferedCursor' ],
            ],
            [
                '_type' => 'mock',
                'id' => 'outer_2',
                'cur' => null,
            ],
        ], Helpers::json_decode($cp->get(CacheProvider::OBJECT_TYPE_BUFFERED_STREAM_ELEMENTS, 'bufcur_f2d633251c9980590abce96f7c3af97a')));

        $this->assertEquals([
            [
                '_type' => 'mock',
                'id' => 'inner_1.1',
                'cur' => null,
            ],
        ], Helpers::json_decode($cp->get(CacheProvider::OBJECT_TYPE_BUFFERED_STREAM_ELEMENTS, 'bufcur_48a575150d9f796896bb6799cee8b2c3')));
    }


    /**
     * Test from_template
     * @return void
     */
    public function test_from_template()
    {
        $cp = new TransientCacheProvider();
        $el1 = new MockMaxStreamElement(345, 'cool', new MockMaxCursor(345));
        $cp->set(CacheProvider::OBJECT_TYPE_BUFFERED_STREAM_ELEMENTS, 'abcd12345', Helpers::json_encode([ $el1->to_template() ]));

        $template = [ '_type' => BufferedCursor::class, 'b' => 'abcd12345' ];
        $bc = StreamSerializer::from_template(new StreamContext($template, [], $cp));
        $this->assertEquals(new BufferedCursor(null, [ $el1 ], $cp), $bc);
    }
}
