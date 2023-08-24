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

namespace Test\Tumblr\StreamBuilder\StreamCursors;

use Tests\Unit\Tumblr\StreamBuilder\TestUtils;
use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Codec\CacheCodec;
use Tumblr\StreamBuilder\Exceptions\InvalidTemplateException;
use Tumblr\StreamBuilder\Exceptions\MissingCacheException;
use Tumblr\StreamBuilder\Exceptions\NoCodecAvailableException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use function str_repeat;
use function get_class;
use function sprintf;

/**
 * Class StreamCursorTest
 */
class StreamCursorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test combine with
     * @return void
     */
    public function test_combine_with_null()
    {
        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $sc */
        $sc =  $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sc->expects($this->any())
            ->method('_can_combine_with')
            ->willReturn(true);

        $this->assertSame($sc, $sc->combine_with(null));
    }

    /**
     * @return void
     */
    public function test_combine_with_false()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\UncombinableCursorException::class);
        /** @var StreamCursor|\PHPUnit\Framework\MockObject\MockObject $sc */
        $sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sc->expects($this->any())
            ->method('_can_combine_with')
            ->willReturn(false);
        $sc->expects($this->any())
            ->method('to_string')
            ->willReturn('sc');

        $another_sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $another_sc->expects($this->any())
            ->method('to_string')
            ->willReturn('another_sc');
        $sc->combine_with($another_sc);
    }

    /**
     * @return void
     */
    public function test_combine_all()
    {
        $sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sc->expects($this->any())
            ->method('_can_combine_with')
            ->willReturn(true);
        $sc->expects($this->any())
            ->method('_combine_with')
            ->willReturn($sc);

        $another_sc = $this->getMockBuilder(StreamCursor::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->assertSame($sc, StreamCursor::combine_all([$sc, $another_sc]));
    }

    /**
     * @return void
     */
    public function test_encode()
    {
        $cursor = new MultiCursor([]);
        $secret = 'let it go';
        $encrypt_key = 'follow the rabbit';

        $this->assertSame(
            'VDG39T8r6pbt4qNS9DHH5_lfdCxc-JXwli-6t8hvu5RNVTZFUkxLR2k5WEFkSS8rcDNyL1JoZHlSUVQ0TE5mdzVKOVFRVjg2K0pEaE' .
            'lWdGRkaER0M2VFQUJ4akZOeGxPa3kxelFiTXBqK3M0elJ1bUQ0cko0RjJGd2ZrWkZ6K3Z0M3hWQU9BWHBCZDA9',
            StreamCursor::encode(
                $cursor,
                $secret,
                $encrypt_key,
                '',
                null,
                StreamCursor::DEFAULT_CACHE_SIZE_THRESHOLD,
                null,
                '123456'
            )
        );
    }

    /**
     * Test encode with cache provider.
     */
    public function test_encode_with_cache_provider()
    {
        // Make a very long string that just triggers CacheCodec,
        // If you see this unit test failed, make sure you understand the consequence
        // when changing the cache threshold.
        $a_very_long_string = str_repeat('bar', 302000);

        $cursor = new MultiCursor([], [
            'odd_state' => $a_very_long_string,
        ]);
        $secret = 'let it go';
        $encrypt_key = 'follow the rabbit';
        $provider =  $this->getMockBuilder(CacheProvider::class)->getMockForAbstractClass();
        $provider->expects($this->once())
            ->method('set');

        $this->assertMatchesRegularExpression(
            '/ckey_[a-z0-9]{32}/',
            Helpers::base64UrlDecode(
                StreamCursor::encode(
                    $cursor,
                    $secret,
                    $encrypt_key,
                    '',
                    $provider,
                    StreamCursor::DEFAULT_CACHE_SIZE_THRESHOLD,
                    null,
                    '123456'
                )
            )
        );
    }

    /**
     * @return void
     */
    public function test_decode()
    {
        $cursor = new MultiCursor([
            'amazing_stream' => new MultiCursor([]),
        ], [
            'next_offset' => 12,
        ]);
        $secret = 'let it go';
        $encrypt_key = 'follow the rabbit';
        $decoded_cursor = StreamCursor::decode(
            StreamCursor::encode(
                $cursor,
                $secret,
                $encrypt_key,
                'does not matter',
                null,
                StreamCursor::DEFAULT_CACHE_SIZE_THRESHOLD,
                null,
                '123456'
            ),
            [$secret],
            [$encrypt_key],
            '123456',
            ['does not matter']
        );
        TestUtils::assertSameRecursively(
            $decoded_cursor,
            $cursor
        );
        $this->assertSame($decoded_cursor->to_template(), $cursor->to_template());
    }

    /**
     * @return void
     */
    public function test_decode_signature_mismatch()
    {
        $secret = 'cannot let it go';
        $encrypt_key = 'follow the rabbit';
        try {
            StreamCursor::decode(
                'tZBND8IgDIb_C-cdGI647KhnT3pzZoFRDAaY4SNRF_67Uziod3tp-7x526YzuqAOzX3EmJAh3K-Qyy6nQzRcu36JfXDAzCYqLeA' .
                'DbKPzk_MvsIs6qNxnc5WTLxPLEmbYQ9nz4N_-b-2_BxxPBagCbNQ6pR9YTrFwC8MkpYdQhJqkhCrkl39RXjNOV1BL2bKWNpI3gm' .
                'CBRwkjXTOK0hM.',
                [$secret],
                [$encrypt_key],
                '123456',
                ['does not matter'],
                null
            );
        } catch (\Exception $e) {
            $this->assertSame(
                NoCodecAvailableException::class,
                get_class($e)
            );
        }
    }

    /**
     * @return array
     */
    public function provider_test_decode_exception()
    {
        return [
            [false, 'whatever', \InvalidArgumentException::class, [], []],
            [false, 'whatever', \InvalidArgumentException::class, ['let it go'], []],
            [false, null, MissingCacheException::class, ['let it go'], ['follow the rabbit']],
            [true, null, MissingCacheException::class, ['let it go'], ['follow the rabbit']],
            [true, 'whatever', InvalidTemplateException::class, ['let it go'], ['follow the rabbit']],
        ];
    }

    /**
     * @dataProvider provider_test_decode_exception
     * @param bool $create_provider Whether to use a mock cache provider.
     * @param mixed $get_returned Returned from cache provider.
     * @param string $exception Expected exception.
     * @param array $secrets Array of available secrets.
     * @param array $encrypt_keys Array of encrypt keys.
     * Test decode with cache key, but not cache provider.
     */
    public function test_decode_exception(
        bool $create_provider,
        $get_returned,
        string $exception,
        array $secrets,
        array $encrypt_keys
    ) {
        $this->expectException($exception);
        $cache_key = sprintf('%s%s', CacheCodec::CACHE_PREFIX, Helpers::get_uuid());
        $url_encoded_cache_key = Helpers::base64UrlEncode($cache_key);

        if ($create_provider) {
            $provider = $this->getMockBuilder(CacheProvider::class)->getMockForAbstractClass();

            /** @var CacheProvider|\PHPUnit\Framework\MockObject\MockObject $provider */
            $provider->expects($this->once())
                ->method('get')
                ->with(2, $cache_key)
                ->willReturn($get_returned);
        } else {
            $provider = null;
        }

        StreamCursor::decode(
            $url_encoded_cache_key,
            $secrets,
            $encrypt_keys,
            '123456',
            [123],
            $provider
        );
    }

    /**
     * Test multiple seeds passed into decode.
     */
    public function testDecodeWithMulitpleSeeds(): void
    {
        $cursor = new MultiCursor([
            'amazing_stream' => new MultiCursor([]),
        ], [
            'next_offset' => 12,
        ]);
        $secret = 'let it go';
        $encrypt_key = 'follow the rabbit';
        $decoded_cursor = StreamCursor::decode(
            StreamCursor::encode(
                $cursor,
                $secret,
                $encrypt_key,
                'does not matter',
                null,
                StreamCursor::DEFAULT_CACHE_SIZE_THRESHOLD,
                null,
                '123456'
            ),
            [$secret],
            ['www', $encrypt_key],
            '123456',
            ['what', 'does not matter']
        );
        TestUtils::assertSameRecursively(
            $decoded_cursor,
            $cursor
        );
        $this->assertSame($decoded_cursor->to_template(), $cursor->to_template());
    }

    /**
     * Test multiple seeds passed into decode.
     */
    public function testDecodeWithWrongSeeds(): void
    {
        $cursor = new MultiCursor([
            'amazing_stream' => new MultiCursor([]),
        ], [
            'next_offset' => 12,
        ]);
        $secret = 'let it go';
        $encrypt_key = 'follow the rabbit';
        $this->expectException(InvalidTemplateException::class);
        StreamCursor::decode(
            StreamCursor::encode(
                $cursor,
                $secret,
                $encrypt_key,
                'does not matter',
                null,
                StreamCursor::DEFAULT_CACHE_SIZE_THRESHOLD,
                null,
                '123456'
            ),
            [$secret],
            ['www', $encrypt_key],
            '123456',
            ['what', 'aaa'],
            null
        );
    }
}
