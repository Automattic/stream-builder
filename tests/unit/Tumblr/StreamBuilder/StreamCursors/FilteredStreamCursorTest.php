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

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\FilteredStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilters\DeduplicatedStreamFilterState;
use Tumblr\StreamBuilder\StreamFilterState;
use Tumblr\StreamBuilder\TransientCacheProvider;

/**
 * Class FilteredStreamCursorTest
 */
class FilteredStreamCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     * @return void
     */
    public function test_to_template_null()
    {
        $cursor = new FilteredStreamCursor();
        $this->assertSame([
            '_type' => FilteredStreamCursor::class,
        ], $cursor->to_template());

        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $original_cursor */
        $original_cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $original_cursor->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'amazing_cursor',
                'p' => 123,
            ]);
        $cursor = new FilteredStreamCursor($original_cursor);
        $this->assertSame($cursor->to_template(), [
            '_type' => FilteredStreamCursor::class,
            'c' => [
                '_type' => 'amazing_cursor',
                'p' => 123,
            ],
        ]);

        $cursor = new FilteredStreamCursor(null, new DeduplicatedStreamFilterState(10, ['abc', 'def'], new TransientCacheProvider()));
        $this->assertSame([
            '_type' => FilteredStreamCursor::class,
            'f' => [
                '_type' => DeduplicatedStreamFilterState::class,
                's' => 10,
                'c' => 'dedup_7d310584de9a98c4e323be77af878f64',
            ],
        ], $cursor->to_template());
    }

    /**
     * Test to_template
     * @return array
     */
    public function test_to_template()
    {
        $cp = new TransientCacheProvider();

        $original_cursor = new MultiCursor([]);
        $state = new DeduplicatedStreamFilterState(77, ['bar', 'baz'], $cp);

        $filtered_cursor = new FilteredStreamCursor($original_cursor, $state);
        $template = [
            '_type' => FilteredStreamCursor::class,
            'c' => [
                '_type' => MultiCursor::class,
                's' => [],
                'i' => null,
            ],
            'f' => [
                '_type' => DeduplicatedStreamFilterState::class,
                's' => 77,
                'c' => 'dedup_350ea44d880e98ca2e838ce27c1b2ec9',
            ],
        ];
        $this->assertSame($template, $filtered_cursor->to_template());

        return [$template, $cp];
    }

    /**
     * Test from_template
     * @depends test_to_template
     * @param array $template_and_cp Tuple of template and CacheProvider returned from test_from_template
     * @return FilteredStreamCursor
     */
    public function test_from_template(array $template_and_cp)
    {
        [$template, $cp] = $template_and_cp;
        $context = new StreamContext($template, [], $cp);
        $cursor = FilteredStreamCursor::from_template($context);
        $this->assertSame($template, $cursor->to_template());

        return $cursor;
    }

    /**
     * Test combine with
     * @param FilteredStreamCursor $cursor The cursor template.
     * @depends test_from_template
     * @return void
     */
    public function test_combine_with(FilteredStreamCursor $cursor)
    {
        $this->assertSame($cursor, $cursor->combine_with(null));

        $other = new FilteredStreamCursor(null, null);
        $this->assertEquals($cursor, $cursor->combine_with($other));

        $other = new FilteredStreamCursor(null, new DeduplicatedStreamFilterState(77, ['foo'], new TransientCacheProvider()));
        /** @var FilteredStreamCursor $combined */
        $combined = $cursor->combine_with($other);
        /** @var DeduplicatedStreamFilterState $filter_state */
        $filter_state = $combined->get_filter_state();

        $this->assertSame(['bar', 'baz', 'foo'], $filter_state->get_seen_items());

        $original_cursor = new MultiCursor([
            'foo' => 123,
            'bar' => 456,
        ]);
        $other = new FilteredStreamCursor($original_cursor, null);
        /** @var FilteredStreamCursor $combined */
        $combined = $cursor->combine_with($other);

        /** @var MultiCursor $inner_cursor */
        $inner_cursor = $combined->get_inner_cursor();
        $this->assertSame('Multi(foo:123; bar:456)', (string) $inner_cursor);
    }

    /**
     * Test to_string
     * @param FilteredStreamCursor $cursor The cursor template.
     * @depends test_from_template
     * @return void
     */
    public function test_to_string(FilteredStreamCursor $cursor)
    {
        $this->assertSame('Filtered(Multi(),Dedup(bar,baz))', (string) $cursor);
    }

    /**
     * @return array
     */
    public function provider_with_filter_state()
    {
        /** @var CacheProvider $provider */
        $provider = $this->getMockBuilder(CacheProvider::class)->getMockForAbstractClass();
        $state = new DeduplicatedStreamFilterState(10, ['foo'], $provider);

        return [
            [null, null],
            [$state, $state],
        ];
    }

    /**
     * Test with_filter_state
     * @dataProvider provider_with_filter_state
     * @param StreamFilterState $state StreamFilterState.
     * @param StreamFilterState $expected_state The expected state.
     */
    public function test_with_filter_state(?StreamFilterState $state = null, ?StreamFilterState $expected_state = null)
    {
        $cursor = new FilteredStreamCursor();
        $cursor = $cursor->with_filter_state($state);
        $this->assertEquals($expected_state, $cursor->get_filter_state());
    }

    /**
     * Test combine_from
     */
    public function test_combine_from()
    {
        $cursor = new FilteredStreamCursor();

        $inner_cursor = new MultiCursor([]);
        /** @var StreamElement|\PHPUnit\Framework\MockObject\MockObject $el */
        $el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool', $inner_cursor])->getMockForAbstractClass();

        $this->assertEquals(
            $cursor->combine_from($el)->to_template(),
            [
                '_type' => FilteredStreamCursor::class,
                'c' => [
                    '_type' => MultiCursor::class,
                    's' => [],
                    'i' => null,
                ],
            ]
        );
    }
}
