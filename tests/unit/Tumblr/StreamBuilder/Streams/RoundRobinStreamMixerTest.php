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

use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\RoundRobinStreamMixer;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function array_map;

/**
 * Class RoundRobinStreamMixerTest
 */
class RoundRobinStreamMixerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array test data for anonymous class.
     */
    public $data;

    /**
     * Data Provider.
     */
    public function invalid_minor_streams_provider()
    {
        return [
            [[1, 2, 3]],
            [['awesome_string']],
            [
                [
                    new class() {
                        // Amazing anonymous class.
                    },
                ],
            ],
            [[null, null]],
            [[true, false]],
        ];
    }

    /**
     * @dataProvider invalid_minor_streams_provider
     * @param array $minors Minor streams.
     */
    public function test_constructor(array $minors)
    {
        $this->expectException(\InvalidArgumentException::class);
        $stream = $this->getMockBuilder(Stream::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $injector = $this->getMockBuilder(StreamInjector::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $this->getMockBuilder(RoundRobinStreamMixer::class)
            ->setConstructorArgs([$stream, $minors, $injector, 'awesome_id'])
            ->getMockForAbstractClass();
    }

    /**
     * Data Provider.
     */
    public function mix_count_provider()
    {
        return [
            [10, [0, 4, 8], ['bar', 'foo', 'baz', 'foo', 'bar', 'baz', 'baz', 'baz', 'bar', 'baz']],
            [1, [0, 4, 8], ['bar']],
            [15, [0, 4, 8], ['bar', 'foo', 'baz', 'foo', 'bar', 'baz', 'baz', 'baz', 'bar', 'baz', 'baz', 'bar']],
            [10, [], ['foo', 'baz', 'foo', 'baz', 'baz', 'baz', 'baz', 'baz', 'bar', 'bar']],
            [4, [], ['foo', 'baz', 'foo', 'baz']],
            [10, [10, 20], ['foo', 'baz', 'foo', 'baz', 'baz', 'baz', 'baz', 'baz', 'bar', 'bar']],
            [10, [8, 20], ['foo', 'baz', 'foo', 'baz', 'baz', 'baz', 'baz', 'baz', 'bar', 'bar']],
            [10, [9, 20], ['foo', 'baz', 'foo', 'baz', 'baz', 'baz', 'baz', 'baz', 'bar', 'bar']],
        ];
    }

    /**
     * Test mix
     * @dataProvider  mix_count_provider
     * @param int $count Mix size.
     * @param int[] $positions Positions for main stream elements.
     * @param string[] $expected_ids The expected mix result.
     */
    public function test_mix(int $count, array $positions, array $expected_ids)
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $main */
        $main = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['main_stream'])
            ->getMockForAbstractClass();
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $minor1 */
        $minor1 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['minor_stream_1'])
            ->getMockForAbstractClass();
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $minor2 */
        $minor2 = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['minor_stream_2'])
            ->getMockForAbstractClass();
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $injector */
        $injector = $this->getMockBuilder(StreamInjector::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $injector->expects($this->any())
            ->method('_plan_injection')
            ->willReturn(new InjectionPlan([]));

        $el1 = $this->get_element('main', 'bar');
        $el2 = $this->get_element('minor1', 'foo');
        $el3 = $this->get_element('minor2', 'baz');

        $main->expects($this->any())
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el1, $el1, $el1, $el1]));

        $minor1->expects($this->any())
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el2, $el2]));

        $minor2->expects($this->any())
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el3, $el3, $el3, $el3, $el3, $el3]));

        /** @var RoundRobinStreamMixer $mixer */
        $mixer = new class($main, [$minor1, $minor2], $injector, 'awesome_id', $positions) extends RoundRobinStreamMixer {
            /** @var int[] */
            private $positions;
            /**
             * @inheritDoc
             */
            public function __construct(Stream $main, $minors, StreamInjector $injector, $identity, array $positions)
            {
                $this->positions = $positions;
                parent::__construct($main, $minors, $injector, $identity);
            }

            /**
             * @inheritDoc
             */
            protected function get_main_stream_positions(int $count, ?StreamTracer $tracer = null): array
            {
                return $this->positions;
            }

            /**
             * @inheritDoc
             */
            public function to_template(): array
            {
                // TODO: Implement to_template() method.
            }

            /**
             * @inheritDoc
             */
            public static function from_template(StreamContext $context): RoundRobinStreamMixer
            {
                // TODO: Implement from_template() method.
            }
        };

        $result = $mixer->enumerate($count, null, null);
        $ids = array_map(function (StreamElement $el) {
            return (string) $el;
        }, $result->get_elements());
        $this->assertSame($ids, $expected_ids);
    }

    /**
     * @param string $provider_identity The identity of the stream providing this element.
     * @param string $id The id.
     * @return StreamElement
     */
    private function get_element(string $provider_identity, string $id): LeafStreamElement
    {
        return new class($provider_identity, $id) extends LeafStreamElement {
            /** @var string */
            private $id;

            /**
             * @inheritDoc
             */
            public function __construct(string $provider_identity, string $id)
            {
                $this->id = $id;
                parent::__construct($provider_identity, null);
            }

            /**
             * @inheritDoc
             */
            public function get_cache_key()
            {
                return null;
            }

            /**
             * @inheritDoc
             */
            protected function to_string(): string
            {
                return $this->id;
            }

            /**
             * @inheritDoc
             */
            public function to_template(): array
            {
                // Testing Shim
            }

            /**
             * @inheritDoc
             */
            public static function from_template(StreamContext $context): self
            {
                // Testing Shim
            }
        };
    }
}
