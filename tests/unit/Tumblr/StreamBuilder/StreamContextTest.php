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

use Tumblr\StreamBuilder\StreamContext;

/**
 * Class StreamContextTest
 */
class StreamContextTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test get_meta_by_key
     */
    public function test_get_meta_by_key()
    {
        $context = new StreamContext(null, [
            'foo' => 123,
            123 => 'bar',
            'baz' => [1, 2, 3],
        ]);

        $this->assertSame($context->get_meta_by_key('foo'), 123);
        $this->assertSame($context->get_meta_by_key('123'), 'bar');
        $this->assertSame($context->get_meta_by_key('baz'), [1, 2, 3]);
    }

    /**
     * Test get_meta
     */
    public function test_get_meta()
    {
        $meta = [
            'foo' => 123,
            123 => 'bar',
            'baz' => [1, 2, 3],
        ];

        $context = new StreamContext(null, $meta);

        $this->assertSame(
            $meta,
            $context->getMeta()
        );
    }

    /**
     * Test derive_property
     */
    public function test_derive_property()
    {
        $context = new StreamContext([
            'injector' => [
                '_type' => 'an_amazing_injector',
            ],
        ], [
            'foo' => 123,
        ]);
        $derived_context = $context->derive_property('injector');

        $this->assertSame([
            '_type' => 'an_amazing_injector',
        ], $derived_context->get_template());
        $this->assertSame(123, $derived_context->get_meta_by_key('foo'));
    }

    /**
     * Test derive property with missing property
     */
    public function test_derive_missing_property()
    {
        $this->expectException(\InvalidArgumentException::class);
        $context = new StreamContext([], []);
        $context->derive_property('foo');
    }

    /**
     * Test get required property with missing property
     */
    public function test_get_required_property_with_missing_property()
    {
        $this->expectException(\InvalidArgumentException::class);
        $context = new StreamContext([], []);
        $context->get_required_property('foo');
    }

    /**
     * Test we can derive component meta.
     */
    public function testDeriveComponentMeta(): void
    {
        $context = new StreamContext([
            '_type' => 'test_stream',
            'inner' => [
                '_type' => 'test_stream_2',
                '_component' => 'XX',
            ],
        ], []);
        $this->assertEmpty($context->get_meta_by_key('_component'));
        $derived = $context->derive_property('inner');
        $this->assertSame('XX', $derived->get_meta_by_key('_component'));
    }

    /**
     * Test we can derive component meta and inherit.
     */
    public function testDeriveComponentMetaInheritance(): void
    {
        $context = new StreamContext([
            '_type' => 'test_stream',
            'inner' => [
                '_type' => 'test_stream_2',
                '_component' => 'XX',
                'inner' => [
                    'type' => 'test_stream_3',
                ],
            ],
        ], []);
        $this->assertEmpty($context->get_meta_by_key('_component'));
        $derived = $context->derive_property('inner');
        $inherited = $derived->derive_property('inner');
        $this->assertSame('XX', $inherited->get_meta_by_key('_component'));
    }

    /**
     * Test we can derive component meta and inheritage could be override.
     */
    public function testDeriveComponentMetaInheritanceOverride(): void
    {
        $context = new StreamContext([
            '_type' => 'test_stream',
            'inner' => [
                '_type' => 'test_stream_2',
                '_component' => 'XX',
                'inner' => [
                    'type' => 'test_stream_3',
                    '_component' => 'YY',
                ],
            ],
        ], []);
        $this->assertEmpty($context->get_meta_by_key('_component'));
        $derived = $context->derive_property('inner');
        $this->assertSame('XX', $derived->get_meta_by_key('_component'));
        $inherited = $derived->derive_property('inner');
        $this->assertSame('YY', $inherited->get_meta_by_key('_component'));
    }
}
