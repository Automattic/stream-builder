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

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\NullCacheProvider;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\FilteredStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamFilters\DeduplicatedStreamFilter;
use Tumblr\StreamBuilder\StreamFilters\DeduplicatedStreamFilterState;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\FilteredStream;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\TransientCacheProvider;
use function is_null;
use function array_map;
use function sprintf;
use function md5;

/**
 * Class DeduplicatedStreamFilterTest
 */
class DeduplicatedStreamFilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test __construct
     * @return void
     */
    public function test_construct_failure()
    {
        $this->expectException(\TypeError::class);
        new DeduplicatedStreamFilter('bar', new NullCacheProvider());
    }

    /**
     * Test get_cache_key
     * @return void
     */
    public function test_get_cache_key()
    {
        // NOTE: get_cache_key should get nothing, this filter is not cache-able
        $filter = new DeduplicatedStreamFilter('ello', 100, new NullCacheProvider());
        $this->assertNull($filter->get_cache_key());
    }

    /**
     * Test filter
     * @return void
     */
    public function test_filter()
    {
        $filter = new DeduplicatedStreamFilter('ello', 100, new NullCacheProvider());
        $el1 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el1->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('bar');
        $el2 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el2->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('foo');
        $el3 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el3->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('foo');
        $el4 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el4->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('faz');

        $cursor = $this->getCursorMock();
        $term_element = $this->build_leaf('cool', $cursor, 'cats');
        $term_element2 = $this->build_leaf('cool', $cursor, 'cat');
        $dup_term_element = $this->build_leaf('cool', $cursor, 'cats');

        $state = new DeduplicatedStreamFilterState(100, ['bar'], new NullCacheProvider());
        $result = $filter->filter([$el1, $el2, $el3, $el4, $term_element, $term_element2, $dup_term_element], $state);

        $this->assertSame(4, $result->get_retained_count());
        $this->assertSame(3, $result->get_released_count());
    }

    /**
     * Test filter window is working.
     * @return void
     */
    public function test_filter_window()
    {
        $filter = new DeduplicatedStreamFilter('ello', 2, new NullCacheProvider());
        $el1 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el1->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('bar');
        $el2 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el2->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('foo');

        $el3 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el3->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('foo');
        $el4 = $this->getMockBuilder(StreamElement::class)->disableOriginalConstructor()->getMock();
        $el4->expects($this->any())
            ->method('get_cache_key')
            ->willReturn('faz');

        $state = new DeduplicatedStreamFilterState(2, ['bar', 'faz'], new NullCacheProvider());
        $result = $filter->filter([$el1, $el2, $el3, $el4], $state);

        $this->assertSame(1, $result->get_retained_count());
        $this->assertSame(3, $result->get_released_count());
    }

    /**
     * Make an anonymous leaf element
     * @param string $stream_id The stream
     * @param StreamCursor $cur The cursor
     * @param string $ck The cache key
     * @return LeafStreamElement
     */
    private function build_leaf(string $stream_id, StreamCursor $cur, string $ck)
    {
        return new class($stream_id, $cur, $ck) extends LeafStreamElement {
            /** @var string Cache key */
            private $cache_key;

            /** @inheritDoc */
            public function __construct(string $provider_identity, StreamCursor $cur, string $cache_key)
            {
                parent::__construct($provider_identity, $cur);
                $this->cache_key = $cache_key;
            }
            /** @inheritDoc */
            public function get_cache_key()
            {
                return $this->cache_key;
            }
            /** @inheritDoc */
            protected function to_string(): string
            {
                return $this->cache_key;
            }
            /** @inheritDoc */
            public function to_template(): array
            {
                // Testing Shim
                return [];
            }

            /** @inheritDoc */
            public static function from_template(StreamContext $context): self
            {
                // Testing Shim
            }
        };
    }

    /**
     * Test that cache can round-trip.
     * @return void
     */
    public function test_cache_round_trip()
    {
        $cp = new TransientCacheProvider();

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)->setConstructorArgs(['AmazingStream'])->getMockForAbstractClass();

        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $cursor */
        $cursor = $this->getCursorMock();

        $el1a = $this->build_leaf('cool', $cursor, 'foo');
        $el1b = $this->build_leaf('cool', $cursor, 'bar');
        $el2a = $this->build_leaf('cool', $cursor, 'foo');
        $el2b = $this->build_leaf('cool', $cursor, 'qux');

        $stream->expects($this->exactly(2))->method('_enumerate')->willReturnCallback(function ($count, $cursor) use ($el1a, $el1b, $el2a, $el2b) {
            if (is_null($cursor)) {
                return new StreamResult(false, [$el1a, $el1b]);
            } elseif ($cursor) {
                return new StreamResult(true, [$el2a, $el2b]);
            } else {
                return new StreamResult(true, []);
            }
        });

        $filtered_stream = new FilteredStream($stream, new DeduplicatedStreamFilter('dup', 10, $cp), 'wat');
        $result1 = $filtered_stream->enumerate(2);
        $result1_cursor = $result1->get_combined_cursor();

        $this->assertSame('Filtered(AmazingCursor,Dedup(bar,foo))', (string) $result1_cursor);
        $this->assertFalse($result1->is_exhaustive());
        $this->assertSame(2, $result1->get_size());
        $this->assertSame([ $el1a, $el1b ], array_map(function (DerivedStreamElement $x) {
            return $x->get_original_element();
        }, $result1->get_elements()));

        $result1_expected_cache_value = 'bar.foo';
        $result1_expected_cache_key = sprintf('%s%s', DeduplicatedStreamFilterState::CACHE_KEY_PREFIX, md5($result1_expected_cache_value));

        $this->assertSame([
            '_type' => FilteredStreamCursor::class,
            'c' => [
                '_type' => 'AmazingCursor',
                'p' => 12,
            ],
            'f' => [
                '_type' => DeduplicatedStreamFilterState::class,
                's' => 10,
                'c' => $result1_expected_cache_key,
            ],
        ], $result1_cursor->to_template());
        $this->assertSame(
            $result1_expected_cache_value,
            $cp->get(
                CacheProvider::OBJECT_TYPE_DEDUPLICATED_FILTER_STATE_MEMORY,
                $result1_expected_cache_key
            )
        );

        $result2 = $filtered_stream->enumerate(2, $result1_cursor);
        $result2_cursor = $result2->get_combined_cursor();

        $this->assertSame('Filtered(AmazingCursor,Dedup(bar,foo,qux))', (string) $result2_cursor);
        $this->assertTrue($result2->is_exhaustive());
        $this->assertSame(1, $result2->get_size());
        $this->assertSame([ $el2b ], array_map(function (DerivedStreamElement $x) {
            return $x->get_original_element();
        }, $result2->get_elements()));

        $result2_expected_cache_value = 'bar.foo.qux';
        $result2_expected_cache_key = sprintf('%s%s', DeduplicatedStreamFilterState::CACHE_KEY_PREFIX, md5($result2_expected_cache_value));

        $this->assertSame([
            '_type' => FilteredStreamCursor::class,
            'c' => [
                '_type' => 'AmazingCursor',
                'p' => 12,
            ],
            'f' => [
                '_type' => DeduplicatedStreamFilterState::class,
                's' => 10,
                'c' => $result2_expected_cache_key,
            ],
        ], $result2_cursor->to_template());
        $this->assertSame(
            $result2_expected_cache_value,
            $cp->get(
                CacheProvider::OBJECT_TYPE_DEDUPLICATED_FILTER_STATE_MEMORY,
                $result2_expected_cache_key
            )
        );
    }

    /**
     * Test to_template
     * @return array
     */
    public function test_to_template()
    {
        $filter = new DeduplicatedStreamFilter('dup', 100, new NullCacheProvider());
        $template = [
            '_type' => DeduplicatedStreamFilter::class,
            'window' => 100,
        ];
        $this->assertSame($template, $filter->to_template());
        return $template;
    }

    /**
     * Test from_template
     * @depends test_to_template
     * @param array $template The filter template
     * @return void
     */
    public function test_from_template(array $template)
    {
        $context = new StreamContext($template, [], null, 'my-id');
        $this->assertTrue(
            new DeduplicatedStreamFilter('my-id', 100, new NullCacheProvider()) ==
            StreamSerializer::from_template($context)
        );
    }

    /**
     * Mock for StreamCursor
     * @return \PHPUnit\Framework\MockObject\MockObject|(StreamCursor&\PHPUnit\Framework\MockObject\MockObject)
     */
    protected function getCursorMock()
    {
        $cursor = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $cursor->expects($this->any())
            ->method('to_string')
            ->willReturn('AmazingCursor');

        $cursor->expects($this->any())
            ->method('_can_combine_with')
            ->willReturn(true);

        $cursor->expects($this->any())
            ->method('_combine_with')
            ->willReturn($cursor);

        $cursor->expects($this->any())
            ->method('to_template')
            ->willReturn([
                '_type' => 'AmazingCursor',
                'p' => 12,
            ]);
        return $cursor;
    }
}
