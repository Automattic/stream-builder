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

use Tumblr\StreamBuilder\Codec\BinaryCodec;
use Tumblr\StreamBuilder\Codec\CacheCodec;
use Tumblr\StreamBuilder\Codec\Codec;
use Tumblr\StreamBuilder\Exceptions\NoCodecAvailableException;
use function get_class;

/**
 * Class CodecTest
 */
class CodecTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Provider detect codec
     * @return array
     */
    public function detect_codec_provider(): array
    {
        return [
            [
                [
                    'encoded' => '',
                    'exception' => NoCodecAvailableException::class,
                ],
            ],
            [
                [
                    'encoded' => '123',
                    'exception' => NoCodecAvailableException::class,
                ],
            ],
            [
                [
                    'encoded' => 'ckey_12345678901234567890123456789012',
                    'codec_type' => CacheCodec::class,
                ],
            ],
            [
                [
                    'encoded' => 'T12345678901234567890123456789012',
                    'codec_type' => BinaryCodec::class,
                ],
            ],
        ];
    }

    /**
     * Test detect codec
     * @param array $inputs The test inputs.
     * @dataProvider detect_codec_provider
     */
    public function test_detect_codec(array $inputs)
    {
        if ($exception = $inputs['exception'] ?? false) {
            $this->expectException($exception);
        }
        $codec = Codec::detect_codec(
            $inputs['encoded'],
            'abc',
            'xyz',
            '123',
            null,
            0,
            '123456'
        );
        $this->assertSame($inputs['codec_type'], get_class($codec));
    }
}
