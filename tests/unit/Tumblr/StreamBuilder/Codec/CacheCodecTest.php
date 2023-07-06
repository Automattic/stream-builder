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

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Codec\CacheCodec;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use function serialize;

/**
 * Class CacheCodecTest
 */
class CacheCodecTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test encode.
     */
    public function test_encode()
    {
        /** @var CacheProvider|\PHPUnit\Framework\MockObject\MockObject $cache_provider */
        $cache_provider = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cache_provider->expects($this->exactly(2))
            ->method('set')
            ->with(2, $this->stringStartsWith('ckey_'), $this->isType('string'))
            ->willReturn(null);

        $cursor = new MultiCursor([], []);
        $this->assertMatchesRegularExpression('/ckey_/', (new CacheCodec($cache_provider, 2))->encode($cursor));
        $this->assertMatchesRegularExpression('/ckey_/', (new CacheCodec($cache_provider, 2, 'JSON'))->encode($cursor));
        try {
            $this->assertMatchesRegularExpression('/ckey_/', (new CacheCodec($cache_provider, 2, 'foo'))->encode($cursor));
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Unsupported serialization type: foo', $e->getMessage());
        }
    }

    /**
     * Decode Provider.
     * @return array
     */
    public function decode_provider(): array
    {
        $cursor = new MultiCursor([], []);
        try {
            $cursor_json = Helpers::json_encode($cursor->to_template());
        } catch (\JsonException $e) {
            // this should not happen, but reset here.
            $cursor_json = null;
        }

        return [
            [
                [
                    'object' => $cursor,
                    'cached' => serialize($cursor),
                    'type' => 'PHP_OBJECT',
                ],
            ],
            [
                [
                    'object' => $cursor,
                    'cached' => $cursor_json,
                    'type' => 'JSON',
                ],
            ],
            [
                [
                    'object' => $cursor,
                    'cached' => null,
                    'type' => 'PHP_OBJECT',
                    'message' => 'Cache key(amazing_key) cannot be found for object type 2',
                ],
            ],
            // Not going to test broken cache data here, since we cannot catch exceptions from `unserialize`
        ];
    }

    /**
     * Test decode.
     * @param array $inputs Test inputs.
     * @dataProvider decode_provider
     */
    public function test_decode(array $inputs)
    {
        /** @var CacheProvider|\PHPUnit\Framework\MockObject\MockObject $cache_provider */
        $cache_provider = $this->getMockBuilder(CacheProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cache_provider->expects($this->once())
            ->method('get')
            ->with(2, 'amazing_key')
            ->willReturn($inputs['cached']);

        $cache_codec = new CacheCodec($cache_provider, 2, $inputs['type']);
        try {
            $this->assertSame($inputs['object']->to_template(), $cache_codec->decode('amazing_key')->to_template());
        } catch (\Exception $e) {
            $this->assertSame($inputs['message'], $e->getMessage());
        }
    }
}
