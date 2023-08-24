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

namespace Tests\Unit\Tumblr\StreamBuilder\Streams;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\ChronologicalBackfillStream;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\Stream;
use function array_map;
use function strval;
use const Tumblr\StreamBuilder\SECONDS_PER_HOUR;

/**
 * Class SizeLimitedStreamTest
 */
class ChronologicalBackfillStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test enumerate no backfill
     */
    public function test_enumerate_no_backfill()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $main_stream */
        $main_stream = $this->getMockBuilder(Stream::class)->setConstructorArgs(['main'])->getMock();
        $main_el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['main'])->getMock();
        $main_stream->expects($this->any())
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$main_el, $main_el, $main_el]));

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $backfill_stream */
        $backfill_stream = $this->getMockBuilder(Stream::class)->setConstructorArgs(['backfill'])->getMock();
        $backfill_el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['backfill'])->getMock();
        $backfill_stream->expects($this->any())
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$backfill_el]));

        $stream = new ChronologicalBackfillStream(
            $main_stream,
            $backfill_stream,
            SECONDS_PER_HOUR,
            'test'
        );
        $result = $stream->enumerate(3);
        $this->assertSame(3, $result->get_size());
        $elements = array_map(
            function (DerivedStreamElement $e) {
                return $e->get_parent_element()->get_provider_identity();
            },
            $result->get_elements()
        );
        $this->assertSame(
            ['main', 'main', 'main'],
            $elements
        );
    }

    /**
     * Test enumeration with backfill
     */
    public function test_enumerate_with_backfill()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $main_stream */
        $main_stream = $this->getMockBuilder(Stream::class)->setConstructorArgs(['main'])->getMock();
        $main_stream->expects($this->any())
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, []));

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $backfill_stream */
        $backfill_stream = $this->getMockBuilder(Stream::class)->setConstructorArgs(['backfill'])->getMock();
        $backfill_el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['backfill'])->getMock();

        $backfill_stream->expects($this->exactly(1))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$backfill_el, $backfill_el]));

        $stream = new ChronologicalBackfillStream(
            $main_stream,
            $backfill_stream,
            SECONDS_PER_HOUR,
            'test'
        );
        $result = $stream->enumerate(3);
        $this->assertSame(2, $result->get_size());
        $elements = array_map(
            function (DerivedStreamElement $e) {
                return $e->get_parent_element()->get_provider_identity();
            },
            $result->get_elements()
        );
        $this->assertSame(
            ['backfill', 'backfill'],
            $elements
        );
    }


    /**
     * Test to_template
     */
    public function test_to_template()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $main_stream */
        $main_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $main_stream->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'main_stream',
            ]);

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $backfill_stream */
        $backfill_stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMock();
        $backfill_stream->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'backfill_stream',
            ]);


        $stream = new ChronologicalBackfillStream(
            $main_stream,
            $backfill_stream,
            SECONDS_PER_HOUR,
            'amazing_chronological_backfill_stream'
        );
        $this->assertSame($stream->to_template(), [
            '_type' => ChronologicalBackfillStream::class,
            'main' => [
                '_type' => 'main_stream',
            ],
            'backfill' => [
                '_type' => 'backfill_stream',
            ],
            'backfill_ts_gap' => SECONDS_PER_HOUR,
        ]);
    }

    /**
     * Test from_template
     */
    public function test_from_template()
    {
        $main_stream = new NullStream('chronological_backfill/main');
        $backfill_stream = new NullStream('chronological_backfill/backfill');
        $stream = new ChronologicalBackfillStream(
            $main_stream,
            $backfill_stream,
            SECONDS_PER_HOUR,
            'chronological_backfill'
        );
        $template = $stream->to_template();
        $context = new StreamContext($template, [], null, 'chronological_backfill');
        $this->assertSame(strval($stream), strval(ChronologicalBackfillStream::from_template($context)));
    }
}
