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

use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Class StreamTest
 */
class StreamTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test constructor
     */
    public function test_constructor_failure()
    {
        $this->expectException(\TypeError::class);
        $this->getMockBuilder(Stream::class)->setConstructorArgs([null])->getMockForAbstractClass();
    }

    /**
     * Test enumerate failure, with exception thrown.
     * @return void
     */
    public function test_enumerate_failure()
    {
        $this->expectException(\Exception::class);
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $stream->expects($this->never())
            ->method('_enumerate');
        $stream->enumerate(-1);
    }

    /**
     * Test enumerate when can_enumerate returns false
     */
    public function test_cannot_enumerate()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->setMethods(['can_enumerate'])
            ->getMockForAbstractClass();

        $stream->expects($this->once())
            ->method('can_enumerate')
            ->willReturn(false);

        $result = $stream->enumerate(10);
        $this->assertTrue($result->is_exhaustive());
        $this->assertSame($result->get_elements(), []);
    }

    /**
     * Test get_identity
     */
    public function test_get_identity()
    {
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['Foo\\Bar\\AmazingStream'])
            ->getMockForAbstractClass();

        $this->assertSame('Foo\\Bar\\AmazingStream', $stream->get_identity());
        $this->assertMatchesRegularExpression('/Foo\\\Bar\\\AmazingStream\[Mock_Stream_[a-z0-9]{8}\]/', $stream->get_identity(true));
    }

    /**
     * Test when empty identity is passed into constructor, an exception should be thrown.
     * @return void
     */
    public function test_empty_identity()
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @var Stream|\PHPUnit\Framework\MockObject\MockObject $stream */
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs([''])
            ->getMockForAbstractClass();
    }

    /**
     * Test that the array_map function in enumerate method is executed
     * This test verifies that the escaped mutant (removal of setComponent call) would be caught
     */
    public function test_enumerate_executes_array_map_function()
    {
        // Create a mock stream that tracks if _enumerate was called
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['test_stream'])
            ->setMethods(['_enumerate'])
            ->getMockForAbstractClass();

        // Create a mock element that tracks component setting
        $mock_element = $this->getMockBuilder(\Tumblr\StreamBuilder\StreamElements\StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_element->method('getComponent')->willReturn(null);
        $mock_element->expects($this->once())
            ->method('setComponent')
            ->with('test_component');

        $stream->method('_enumerate')
            ->willReturn(new \Tumblr\StreamBuilder\StreamResult(false, [$mock_element]));

        // Set the component on the stream
        $stream->setComponent('test_component');

        // Enumerate and verify the element's setComponent was called
        $result = $stream->enumerate(1);
        $this->assertCount(1, $result->get_elements());
    }

    /**
     * Test that elements with existing components are not overridden
     */
    public function test_enumerate_does_not_override_existing_component()
    {
        // Create a mock stream
        $stream = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs(['test_stream'])
            ->setMethods(['_enumerate'])
            ->getMockForAbstractClass();

        // Create a mock element that already has a component
        $mock_element = $this->getMockBuilder(\Tumblr\StreamBuilder\StreamElements\StreamElement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_element->method('getComponent')->willReturn('existing_component');
        $mock_element->expects($this->never())
            ->method('setComponent');

        $stream->method('_enumerate')
            ->willReturn(new \Tumblr\StreamBuilder\StreamResult(false, [$mock_element]));

        // Set a component on the stream
        $stream->setComponent('test_component');

        // Enumerate and verify setComponent was not called
        $result = $stream->enumerate(1);
        $this->assertCount(1, $result->get_elements());
    }
}
