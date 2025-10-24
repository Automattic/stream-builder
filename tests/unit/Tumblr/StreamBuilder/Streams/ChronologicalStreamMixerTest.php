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

use Test\Mock\Tumblr\StreamBuilder\StreamElements\MockedPostRefElement;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamInjectors\NoopInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\ChronologicalStreamMixer;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\Stream;
use const Tumblr\StreamBuilder\QUERY_SORT_ASC;
use const Tumblr\StreamBuilder\QUERY_SORT_DESC;

/**
 * Class ChronologicalStreamMixerTest
 */
class ChronologicalStreamMixerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * To get a chronological stream element.
     * @param string $provider_identity The provider id.
     * @param int $ts The timestamp
     * @return mixed
     */
    private function get_chronological_element(string $provider_identity, int $ts)
    {
        return new class($provider_identity, null, $ts) extends LeafStreamElement implements ChronologicalStreamElement {
            /**
             * @var int
             */
            private $ts;

            /**
             * @inheritDoc
             */
            public function __construct(string $provider_identity, $cursor, int $ts)
            {
                parent::__construct($provider_identity, $cursor);
                $this->ts = $ts;
            }

            /**
             * @inheritDoc
             */
            public function get_timestamp_ms(): int
            {
                return $this->ts;
            }

            /**
             * @inheritDoc
             */
            public function get_cache_key(): string
            {
                return '';
            }

            /**
             * @inheritDoc
             */
            protected function to_string(): string
            {
                return '';
            }

            /**
             * @inheritDoc
             */
            public function to_template(): array
            {
                return [];
            }

            /**
             * @inheritDoc
             */
            public static function from_template(StreamContext $context): self
            {
                return new self();
            }
        };
    }

    /**
     * Data Provider
     */
    public function mix_provider()
    {
        $empty_mixer = new ChronologicalStreamMixer(new NoopInjector('noop'), 'amazing_mixer', [], QUERY_SORT_DESC);

        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream_1 */
        $stream_1 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream_1'])->setMethods(['_enumerate'])->getMockForAbstractClass();
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream_2 */
        $stream_2 = $this->getMockBuilder(Stream::class)->setConstructorArgs(['stream_2'])->setMethods(['_enumerate'])->getMockForAbstractClass();

        $el_1 = new DerivedStreamElement($this->get_chronological_element('cool1', 1400000000000), 'outer');
        $el_2 = new DerivedStreamElement($this->get_chronological_element('cool1', 1400000000002), 'outer');
        $el_3 = $this->get_chronological_element('cool2', 1400000000001);
        $el_4 = $this->get_chronological_element('cool2', 1400000000003);
        $el_5 = $this->get_chronological_element('cool2', 1400000000010);
        $el_6 = $this->get_chronological_element('cool2', 1400000000011);

        $stream_1->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el_1, $el_2]));
        $stream_2->expects($this->exactly(2))
            ->method('_enumerate')
            ->willReturn(new StreamResult(false, [$el_3, $el_4, $el_5, $el_6]));

        $desc_mixer = new ChronologicalStreamMixer(new NoopInjector('noop'), 'amazing_mixer', [
            $stream_1,
            $stream_2,
        ], QUERY_SORT_DESC);

        $asc_mixer = new ChronologicalStreamMixer(new NoopInjector('noop'), 'amazing_mixer', [
            $stream_1,
            $stream_2,
        ], QUERY_SORT_ASC);

        return [
            [$empty_mixer, 0, [], []],
            [
                $desc_mixer,
                5,
                [1400000000011, 1400000000010, 1400000000003, 1400000000002, 1400000000001],
                [
                    null,
                    null,
                    null,
                    DerivedStreamElement::class,
                    null,
                ],
            ],
            [
                $asc_mixer,
                5,
                [1400000000000, 1400000000001, 1400000000002, 1400000000003, 1400000000010],
                [
                    DerivedStreamElement::class,
                    null,
                    DerivedStreamElement::class,
                    null,
                    null,
                ],
            ],
        ];
    }

    /**
     * Test mix
     * @dataProvider mix_provider
     * @param ChronologicalStreamMixer $mixer The ChronologicalStreamMixer.
     * @param int $size The size of elements.
     * @param int[] $timestamps The mixed elements.
     * @param string[] $types Element types.
     */
    public function test_mix(ChronologicalStreamMixer $mixer, int $size, array $timestamps, array $types)
    {
        $res = $mixer->enumerate(5);
        $this->assertSame($res->get_size(), $size);
        $this->assertSame(array_map(function (DerivedStreamElement $el) {
            $original = $el->get_original_element();
            /** @var ChronologicalStreamElement $original */
            return $original->get_timestamp_ms();
        }, $res->get_elements()), $timestamps);
        $this->assertSame(array_map(function (DerivedStreamElement $el) {
            if (DerivedStreamElement::class === $class = get_class($el->get_parent_element())) {
                // This is totally a hack, cannot figure out how to assert anonymous class object type...
                return $class;
            }
        }, $res->get_elements()), $types);
    }

    /**
     * Test to template
     */
    public function test_to_template()
    {
        $stream_1 = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $stream_1->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'amazing_stream_1',
            ]);

        $stream_2 = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $stream_2->expects($this->once())
            ->method('to_template')
            ->willReturn([
                '_type' => 'amazing_stream_2',
            ]);

        $mixer = new ChronologicalStreamMixer(
            new NoopInjector('noop'),
            'amazing_mixer',
            [
                $stream_1,
                $stream_2,
            ],
            QUERY_SORT_DESC
        );

        $template = [
            '_type' => ChronologicalStreamMixer::class,
            'stream_injector' => [
                '_type' => NoopInjector::class,
            ],
            'stream_array' => [
                [
                    '_type' => 'amazing_stream_1',
                ],
                [
                    '_type' => 'amazing_stream_2',
                ],
            ],
            'order' => QUERY_SORT_DESC,
        ];
        $this->assertSame($mixer->to_template(), $template);
    }

    /**
     * @return void
     */
    public function test_from_template()
    {
        $template = [
            '_type' => ChronologicalStreamMixer::class,
            'stream_injector' => [
                '_type' => NoopInjector::class,
            ],
            'stream_array' => [
                [
                    '_type' => NullStream::class,
                ],
                [
                    '_type' => NullStream::class,
                ],
            ],
            'order' => QUERY_SORT_DESC,
        ];
        $stream = ChronologicalStreamMixer::from_template(new StreamContext($template, []));
        $this->assertSame($stream->to_template(), $template);
    }

    /**
     * Test when single stream in stream array exhaust, it should keep enumerating other streams.
     */
    public function testSingleStreamExhaust(): void
    {
        $element = new MockedPostRefElement(1, 1);
        $stream_exhaust = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['a'])
            ->getMockForAbstractClass();
        $stream_exhaust->method('_enumerate')
            ->willReturn(new StreamResult(true, []));
        $stream_keep = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['b'])
            ->getMockForAbstractClass();
        $stream_keep->method('_enumerate')
            ->willReturn(new StreamResult(false, array_fill(0, 20, $element)));
        $stream = new ChronologicalStreamMixer(
            new NoopInjector('w'),
            's',
            [$stream_exhaust, $stream_keep],
            QUERY_SORT_DESC
        );
        $result1 = $stream->enumerate(10);
        $this->assertFalse($result1->is_exhaustive());
        $result2 = $stream->enumerate(10);
        $this->assertFalse($result2->is_exhaustive());
    }

    /**
     * Test when single stream in stream array throw exception, it should keep enumerating other streams.
     */
    public function testSingleStreamException(): void
    {
        $element = new MockedPostRefElement(1, 1);
        $stream_exhaust = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['a'])
            ->getMockForAbstractClass();
        $stream_exhaust->method('_enumerate')
            ->willThrowException(new \InvalidArgumentException());
        $stream_keep = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['b'])
            ->getMockForAbstractClass();
        $stream_keep->method('_enumerate')
            ->willReturn(new StreamResult(false, array_fill(0, 20, $element)));
        $stream = new ChronologicalStreamMixer(
            new NoopInjector('w'),
            's',
            [$stream_exhaust, $stream_keep],
            QUERY_SORT_DESC
        );
        $result1 = $stream->enumerate(10);
        $this->assertFalse($result1->is_exhaustive());
        $result2 = $stream->enumerate(10);
        $this->assertFalse($result2->is_exhaustive());
    }

    /**
     * Test that pre_fetch_all is called on elements during mixing
     * This test verifies that the escaped mutant (removal of pre_fetch_all call) would be caught
     */
    public function test_pre_fetch_all_is_called_on_elements(): void
    {
        // Create a test element that tracks pre_fetch calls
        $test_element = new class('test_provider', null, 1400000000000) extends LeafStreamElement implements ChronologicalStreamElement {
            public $pre_fetch_called = false;
            private $ts;

            public function __construct(string $provider_identity, $cursor, int $ts)
            {
                parent::__construct($provider_identity, $cursor);
                $this->ts = $ts;
            }

            public function get_timestamp_ms(): int
            {
                return $this->ts;
            }

            public static function pre_fetch(array $elements): void
            {
                // Mark that pre_fetch was called by setting a static property
                // We'll use a simple approach to track this
                foreach ($elements as $element) {
                    if ($element instanceof self) {
                        $element->pre_fetch_called = true;
                    }
                }
            }

            public function get_cache_key(): string
            {
                return 'test_cache_key';
            }

            protected function to_string(): string
            {
                return 'test_element';
            }

            public function to_template(): array
            {
                return [];
            }

            public static function from_template(StreamContext $context): self
            {
                return new self('test_provider', null, 1400000000000);
            }
        };

        // Create a mock stream that returns our test element
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['test_stream'])
            ->getMockForAbstractClass();

        $stream->method('_enumerate')
            ->willReturn(new StreamResult(false, [$test_element]));

        $mixer = new ChronologicalStreamMixer(
            new NoopInjector('test_injector'),
            'test_mixer',
            [$stream],
            QUERY_SORT_DESC
        );

        // Enumerate to trigger the mixing process
        $result = $mixer->enumerate(1);

        // Verify that pre_fetch was called on the element
        $this->assertTrue($test_element->pre_fetch_called);
        $this->assertCount(1, $result->get_elements());
    }

    /**
     * Test that pre_fetch_all is called on multiple elements during mixing
     */
    public function test_pre_fetch_all_is_called_on_multiple_elements(): void
    {
        // Create test elements that track pre_fetch calls
        $element1 = new class('test_provider1', null, 1400000000000) extends LeafStreamElement implements ChronologicalStreamElement {
            public $pre_fetch_called = false;
            private $ts;

            public function __construct(string $provider_identity, $cursor, int $ts)
            {
                parent::__construct($provider_identity, $cursor);
                $this->ts = $ts;
            }

            public function get_timestamp_ms(): int
            {
                return $this->ts;
            }

            public static function pre_fetch(array $elements): void
            {
                // Mark that pre_fetch was called by setting a static property
                // We'll use a simple approach to track this
                foreach ($elements as $element) {
                    if ($element instanceof self) {
                        $element->pre_fetch_called = true;
                    }
                }
            }

            public function get_cache_key(): string
            {
                return 'test_cache_key1';
            }

            protected function to_string(): string
            {
                return 'test_element1';
            }

            public function to_template(): array
            {
                return [];
            }

            public static function from_template(StreamContext $context): self
            {
                return new self('test_provider1', null, 1400000000000);
            }
        };

        $element2 = new class('test_provider2', null, 1400000000001) extends LeafStreamElement implements ChronologicalStreamElement {
            public $pre_fetch_called = false;
            private $ts;

            public function __construct(string $provider_identity, $cursor, int $ts)
            {
                parent::__construct($provider_identity, $cursor);
                $this->ts = $ts;
            }

            public function get_timestamp_ms(): int
            {
                return $this->ts;
            }

            public static function pre_fetch(array $elements): void
            {
                // Mark that pre_fetch was called by setting a static property
                // We'll use a simple approach to track this
                foreach ($elements as $element) {
                    if ($element instanceof self) {
                        $element->pre_fetch_called = true;
                    }
                }
            }

            public function get_cache_key(): string
            {
                return 'test_cache_key2';
            }

            protected function to_string(): string
            {
                return 'test_element2';
            }

            public function to_template(): array
            {
                return [];
            }

            public static function from_template(StreamContext $context): self
            {
                return new self('test_provider2', null, 1400000000001);
            }
        };

        // Create a mock stream that returns our test elements
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['test_stream'])
            ->getMockForAbstractClass();

        $stream->method('_enumerate')
            ->willReturn(new StreamResult(false, [$element1, $element2]));

        $mixer = new ChronologicalStreamMixer(
            new NoopInjector('test_injector'),
            'test_mixer',
            [$stream],
            QUERY_SORT_DESC
        );

        // Enumerate to trigger the mixing process
        $result = $mixer->enumerate(2);

        // Verify that pre_fetch was called on both elements
        $this->assertTrue($element1->pre_fetch_called);
        $this->assertTrue($element2->pre_fetch_called);
        $this->assertCount(2, $result->get_elements());
    }
}
