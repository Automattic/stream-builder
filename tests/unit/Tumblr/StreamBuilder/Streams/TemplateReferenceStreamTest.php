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

namespace Test\Tumblr\StreamBuilder\Streams;

use Tests\Unit\Tumblr\StreamBuilder\TestUtils;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\Streams\TemplateReferenceStream;

class TemplateReferenceStreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test from_template method
     */
    public function testFromTemplate()
    {
        $template = [
            '_type' => TemplateReferenceStream::class,
            'name' => 'empty',
            'ctx' => 'examples',
            'identity' => 'amazing identity',
        ];

        $context = new StreamContext($template, []);
        $stream = TemplateReferenceStream::from_template($context);
        $null_stream = new NullStream('amazing identity');
        $this->assertSame(
            (new TemplateReferenceStream($null_stream, 'amazing identity'))->to_template(),
            $stream->to_template()
        );
    }

    /**
     * Test enumerate
     * @return void
     */
    public function testEnumerate()
    {
        $inner_stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $inner_stream->expects($this->once())
            ->method('_enumerate')
            ->willReturn(StreamResult::create_empty_result());

        $stream = new TemplateReferenceStream($inner_stream, 'amazing identity');
        $result = $stream->enumerate(50);
        TestUtils::assertSameRecursively($result, StreamResult::create_empty_result());
    }
}
