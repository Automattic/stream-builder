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

namespace Test\Tumblr\StreamBuilder\StreamFilters;

use Tests\Unit\Tumblr\StreamBuilder\TestUtils;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamFilters\ChronologicalRangeFilter;
use Tumblr\StreamBuilder\StreamSerializer;
use function array_merge;
use function array_slice;
use function array_map;
use function range;
use function sprintf;

/**
 * Test for {@see ChronologicalRangeFilter}
 */
class ChronologicalRangeFilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     */
    public function test_bad_constructor__both_null()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChronologicalRangeFilter('ello', null, null, false);
    }

    /**
     * @return void
     */
    public function test_bad_constructor__equal()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChronologicalRangeFilter('ello', 1000, 1000, false);
    }

    /**
     * @return void
     */
    public function test_bad_constructor__reverse()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChronologicalRangeFilter('ello', 500, 600, false);
    }

    /**
     * @return void
     */
    public function test_get_cache_key()
    {
        $f = new ChronologicalRangeFilter('ello', 2000, 1000, true);
        $this->assertNull($f->get_cache_key());
    }

    /**
     * @return void
     */
    public function test_templating()
    {
        $f = new ChronologicalRangeFilter('ello', 2000, 1000, true);
        $template = $f->to_template();
        TestUtils::assertSameRecursively([
            '_type' => ChronologicalRangeFilter::class,
            'max_timestamp_ms_inclusive' => 2000,
            'min_timestamp_ms_exclusive' => 1000,
            'release_non_chrono' => true,
        ], $template);
        TestUtils::assertSameRecursively($f, StreamSerializer::from_template(new StreamContext($template, [])));
    }

    /**
     * @return void
     */
    public function test_templating__default()
    {
        TestUtils::assertSameRecursively(
            new ChronologicalRangeFilter('ello', 2000, 1000, false),
            StreamSerializer::from_template(new StreamContext([
                '_type' => ChronologicalRangeFilter::class,
                'max_timestamp_ms_inclusive' => 2000,
                'min_timestamp_ms_exclusive' => 1000,
            ], []))
        );
    }

    /**
     * @return void
     */
    public function test_release()
    {
        $e = array_map(function (int $i) {
            return $this->get_mock_chrono_elem($i);
        }, range(0, 7));

        $f = new ChronologicalRangeFilter('ello', 5, 2, true);

        $res = $f->filter($e);
        TestUtils::assertSameRecursively(3, $res->get_retained_count());
        TestUtils::assertSameRecursively(5, $res->get_released_count());
        TestUtils::assertSameRecursively(array_slice($e, 3, 3), $res->get_retained());
        TestUtils::assertSameRecursively(array_merge(array_slice($e, 0, 3), array_slice($e, 6, 2)), $res->get_released());
    }

    /**
     * @return void
     */
    public function test_release_non_chrono()
    {
        $e = array_map(function (int $i) {
            if (($i % 2) == 1) {
                return $this->get_mock_nonchrono_elem();
            } else {
                return $this->get_mock_chrono_elem($i);
            }
        }, range(0, 7));

        $f = new ChronologicalRangeFilter('ello', 5, 2, true);

        $res = $f->filter($e);
        TestUtils::assertSameRecursively(1, $res->get_retained_count());
        TestUtils::assertSameRecursively(7, $res->get_released_count());
        TestUtils::assertSameRecursively([$e[4]], $res->get_retained());
        TestUtils::assertSameRecursively(array_merge(array_slice($e, 0, 4), array_slice($e, 5, 3)), $res->get_released());
    }

    /**
     * @return void
     */
    public function test_retain_non_chrono()
    {
        $e = array_map(function (int $i) {
            if (($i % 2) == 1) {
                return $this->get_mock_nonchrono_elem();
            } else {
                return $this->get_mock_chrono_elem($i);
            }
        }, range(0, 7));

        $f = new ChronologicalRangeFilter('ello', 5, 2, false);

        $res = $f->filter($e);
        TestUtils::assertSameRecursively(5, $res->get_retained_count());
        TestUtils::assertSameRecursively(3, $res->get_released_count());
        TestUtils::assertSameRecursively([$e[1], $e[3], $e[4], $e[5], $e[7]], $res->get_retained());
        TestUtils::assertSameRecursively([$e[0], $e[2], $e[6]], $res->get_released());
    }

    /**
     * @return LeafStreamElement|\PHPUnit\Framework\MockObject\MockObject
     */
    private function get_mock_nonchrono_elem()
    {
        return $this->getMockBuilder(LeafStreamElement::class)
            ->setConstructorArgs(['whatever2', null])
            ->getMockForAbstractClass();
    }

    /**
     * @param int $timestamp_ms The timestamp.
     * @return LeafStreamElement|ChronologicalStreamElement
     * @throws \BadMethodCallException JUST KIDDING, it doesn't, but PHPCBF thinks it does :D.
     */
    private function get_mock_chrono_elem(int $timestamp_ms)
    {
        return new class($timestamp_ms) extends LeafStreamElement implements ChronologicalStreamElement {
            /** @var int */
            private $timestamp_ms;
            /** @param int $timestamp_ms */
            public function __construct(int $timestamp_ms)
            {
                parent::__construct('whatever', null);
                $this->timestamp_ms = $timestamp_ms;
            }
            /** @inheritDoc */
            public function get_timestamp_ms(): int
            {
                return $this->timestamp_ms;
            }
            /** @inheritDoc */
            public function get_cache_key()
            {
                return $this->to_string();
            }
            /** @inheritDoc */
            protected function to_string(): string
            {
                return sprintf('mock(%d)', $this->timestamp_ms);
            }
            /** @inheritDoc */
            public function to_template(): array
            {
                throw new \BadMethodCallException('not implemented');
            }
            /** @inheritDoc */
            public static function from_template(StreamContext $context)
            {
                throw new \BadMethodCallException('not implemented');
            }
        };
    }
}
