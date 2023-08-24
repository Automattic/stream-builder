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

use Tumblr\StreamBuilder\SignalFetchers\TimestampFetcher;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function sprintf;

/**
 * Tests for TimestampFetcher
 */
class TimestampFetcherTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test for fetch()
     * @return void
     */
    public function test_fetch()
    {
        /** @var Stream $provider */
        $provider = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['cool'])
            ->getMockForAbstractClass();


        $tracer = $this->getMockBuilder(StreamTracer::class)
            ->getMockForAbstractClass();

        /** @var StreamElement $el1 */
        $el1 = $this->make_chrono_element($provider->get_identity(), 1234567);

        $tf = new TimestampFetcher('cool');
        $sb = $tf->fetch([ $el1 ], $tracer);
        $this->assertSame(1234567, $sb->get_signal_for_element($el1, TimestampFetcher::SIGNAL_KEY));
    }

    /**
     * Test for get_identity()
     * @return void
     */
    public function test_get_identity()
    {
        $tf = new TimestampFetcher('cool');
        $this->assertSame('cool', $tf->get_identity(false));
        $this->assertSame('cool[TimestampFetcher]', $tf->get_identity(true));
    }


    /**
     * Test for to_template()
     * @return void
     */
    public function test_to_template()
    {
        $tf = new TimestampFetcher('cool');
        $this->assertSame([ '_type' => TimestampFetcher::class ], $tf->to_template());
    }

    /**
     * Test for to_template()
     * @return void
     */
    public function test_from_template()
    {
        $sc = new StreamContext([], [], null, 'whatever/you/like');
        $tf = TimestampFetcher::from_template($sc);
        $this->assertSame('whatever/you/like', $tf->get_identity(false));
        $this->assertSame('whatever/you/like[TimestampFetcher]', $tf->get_identity(true));
    }

    /**
     * Helper function to make a stub chronological stream element.
     * @param string $provider_identity The identity of the stream providing this element.
     * @param int $timestamp_ms The timestamp
     * @return StreamElement The stub element.
     */
    private function make_chrono_element(string $provider_identity, int $timestamp_ms): StreamElement
    {
        return new class($provider_identity, $timestamp_ms) extends LeafStreamElement implements ChronologicalStreamElement {
            /** @var int */
            private $timestamp_ms;
            /**
             * @param string $provider_identity The identity of the stream providing this element.
             * @param int $timestamp_ms The timestamp.
             */
            public function __construct(string $provider_identity, int $timestamp_ms)
            {
                parent::__construct($provider_identity);
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
                return null;
            }
            /** @inheritDoc */
            protected function to_string(): string
            {
                return sprintf('ChronoTest(%d)', $this->timestamp_ms);
            }
            /** @inheritDoc */
            public function to_template(): array
            {
                // Testing Shim
            }
            /** @inheritDoc */
            public static function from_template(StreamContext $context): self
            {
                // Testing Shim
            }
        };
    }
}
