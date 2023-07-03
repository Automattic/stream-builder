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

use Tumblr\StreamBuilder\Exceptions\InvalidStreamArrayException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamFilters\DeduplicatedStreamFilter;
use Tumblr\StreamBuilder\StreamInjectors\NoopInjector;
use Tumblr\StreamBuilder\Streams\ConcatenatedStream;
use Tumblr\StreamBuilder\Streams\FilteredStream;
use Tumblr\StreamBuilder\Streams\InjectedStream;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\Templatable;

/**
 * Class StreamSerializerTest
 */
class StreamSerializerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to_template
     */
    public function test_to_template()
    {
        /** @var Templatable|\PHPUnit\Framework\MockObject\MockObject $templatable */
        $templatable = $this->getMockBuilder(Templatable::class)
            ->disableOriginalConstructor()
            ->setMethods(['to_template'])
            ->getMockForAbstractClass();
        $templatable->expects($this->once())
            ->method('to_template')
            ->with();

        StreamSerializer::to_template($templatable);
    }

    /**
     * @return array
     */
    public function provider_from_template()
    {
        return [
            [
                null,
                InvalidStreamArrayException::class,
            ],
            [
                [],
                InvalidStreamArrayException::class,
            ],
            [
                [
                    'type' => 'bar',
                ],
                InvalidStreamArrayException::class,
            ],
            [
                [
                    '_type' => 'amazing_class',
                ],
                InvalidStreamArrayException::class,
            ],
        ];
    }

    /**
     * @dataProvider provider_from_template
     * @param array|null $template The template to serialize.
     * @param string $error_type Error type.
     */
    public function test_from_template_exception(?array $template, $error_type)
    {
        $this->expectException($error_type);

        $context = new StreamContext($template, []);
        StreamSerializer::from_template($context);
    }

    /**
     * Test from template with skip component set.
     * @return void
     */
    public function testFromTemplateWithSkippedComponents(): void
    {
        $template = [
            '_type' => ConcatenatedStream::class,
            'streams' => [
                ['_type' => NullStream::class, StreamContext::COMPONENT_NAME => 'test'],
                [
                    '_type' => FilteredStream::class,
                    'stream' => [
                        '_type' => InjectedStream::class,
                        StreamContext::COMPONENT_NAME => 'test',
                        'injector' => ['_type' => NoopInjector::class],
                        'stream' => ['_type' => NullStream::class],
                    ],
                    'stream_filter' => ['_type' => DeduplicatedStreamFilter::class],
                ],
            ],
        ];
        /** @var ConcatenatedStream $stream */
        $stream = StreamSerializer::from_template(
            new StreamContext($template, [StreamContext::SKIP_COMPONENT_META => ['test']])
        );
        $this->assertFalse($stream->isSkippedComponent());
        $this->assertTrue($stream->getStreams()[0]->isSkippedComponent());
        /** @var FilteredStream $filtered_stream */
        $filtered_stream = $stream->getStreams()[1];
        $this->assertFalse($filtered_stream->isSkippedComponent());
        $this->assertTrue($filtered_stream->getInner()->isSkippedComponent());
    }
}
