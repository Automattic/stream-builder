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

namespace Test\Tumblr\StreamBuilder\StreamTracers;

use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\DebugStreamTracer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function fopen;
use function rewind;
use function fread;
use function fclose;

/**
 * Class DebugStreamTracerTest
 */
class DebugStreamTracerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test trace event method that should print out all the info in $meta.
     */
    public function test_trace_event()
    {
        $tracer = new DebugStreamTracer();
        $stream = $this->getMockForAbstractClass(Stream::class, ['stream 1']);
        $tracer->trace_event(
            StreamTracer::CATEGORY_ENUMERATE,
            $stream,
            StreamTracer::EVENT_END,
            [2007.0, 1.0],
            [
                'This is awesome' => 'true',
                'I agree' => 'Woo',
            ]
        );
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=enumerate sender=stream 1.* status=end start_time=2007 ' .
            'duration=1 other=\\{"This is awesome":"true","I agree":"Woo"\\}/',
            $tracer->get_output()[0]
        );
    }

    /**
     * Test create DebugStreamTracer with resource should not throw exception.
     */
    public function test_resource()
    {
        $resource = fopen('php://memory', 'w+');
        $tracer = new DebugStreamTracer($resource);
        $stream = $this->getMockForAbstractClass(Stream::class, ['stream 1']);
        $tracer->trace_event(
            StreamTracer::CATEGORY_ENUMERATE,
            $stream,
            StreamTracer::EVENT_END,
            [2007.0, 1.0],
            [
                'This is awesome' => 'true',
                'I agree' => 'Woo',
            ]
        );
        rewind($resource);
        $this->assertMatchesRegularExpression(
            '/\\[.*?\\]: op=enumerate sender=stream 1.* status=end start_time=2007 ' .
            'duration=1 other=\\{"This is awesome":"true","I agree":"Woo"\\}/',
            fread($resource, 200)
        );
        fclose($resource);
    }
}
