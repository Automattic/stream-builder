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
use Tumblr\StreamBuilder\Exceptions\InvalidTemplateException;
use Tumblr\StreamBuilder\NullCacheProvider;
use Tumblr\StreamBuilder\StreamCursors\FilteredStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\GlobalPositionCursor;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\SearchStreamCursor;
use Tumblr\StreamBuilder\Streams\NullStream;
use function bin2hex;
use function hex2bin;
use function get_class;

/**
 * Class BinaryCodecTest
 */
class BinaryCodecTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider encode.
     * @return array
     */
    public function encode_provider(): array
    {
        return [
            [
                [
                    'object' => new MultiCursor([], []),
                    'encoded' => '54234cc3310015882e4e4cd73b3364c81e2eabf9df23fa3e3f3efa8cd1c08a908e2b74656954746e4f516e6b7151545a5263567867344951654e4377647137524b38536a7245442f6b7258467750502f75526971706153306d32746436644243667469585564664a793454674c766b4c592b344e3673413d3d',
                ], // encode a cursor
            ],
            [
                [
                    'object' => new NullStream('foo'),
                    'encoded' => '54cff06d96bc9321077363dc82180438dd6455ba921072701ff1c6d15b8c3bb4132b74656954746e4f516e6b7151545a5263567867344951654e4377647137524b38536a7245442f6b7258474a672b486c4b3473474f32696b38344e2b4b6b2f35',
                ], // encode a stream
            ],
            [
                [
                    'object' => new MultiCursor([], []),
                    'seed' => '456',
                    'encoded' => '54fe06f8f14e35c235e8f7178f07aa7faf2f87ca98090e86ba3c7864bf2015a19142536e6f495977716b5a6b5154433872426a4f377943676e6b70537744784a4c47743758703239596b746578566339545068684f7535693945323833787a515057686e4741623643667a5945393963506935395150773d3d',
                ], // encode a cursor with seed
            ],
            [
                [
                    'object' => new MultiCursor([], []),
                    'seed' => '123bar',
                    'encoded' => '54fcf54352bcf92139bbbc0346fde7e6c6de2c438a19737993d966121028385b726f573872716e69576a64776763544d386c7a79455a4e764b6a444161755a5257524d70384a5037327a724e2b345876466858333653305a4659686445716e4d5377615776464f6c654453513679636d653165776753773d3d',
                ], // encode a cursor with salt
            ],
            [
                [
                    'object' => new GlobalPositionCursor(
                        new SearchStreamCursor(64),
                        64
                    ),
                    'seed' => '123bar',
                    'encoded' => '546be8d93f5613f379141511a59fb5d48b10e8ca800a77dbb07e8cfc4b1d8febe26f573872716e69576a64776763544d386c7a79455a4e764b6a444161755a5257524d70384a5037327a724e5335497a6d74366a7058552f6f2b5850706a6a374b31784f2f617367794e6b4e34324a45504a334b4771526958596a4a39316e2f6d79334b4e777668785470444d48484b33525a6d524531366f474268482b396358484b424f79556c454b4c4e4e524e7466436d71684d773d3d',
                ], // encode a cursor could generate trailing null bytes
            ],
            [
                [
                    'object' => new FilteredStreamCursor(null, null),
                    'seed' => '123bar',
                    'encoded' => '54768faabcc5045bae1a9a6be529ea7eab3548500e77fe9509f5df8b32eee3e4ab6f573872716e69576a64776763544d386c7a79455a4e764b6a444161755a5257524d70384a5037327a724d4b4f366b364a64783939756961376e69735871454c306864583250553076712b6339454f4b544a5a5474773d3d',
                ],
            ],
        ];
    }

    /**
     * Test encode method.
     * @param array $inputs Test inputs.
     * @dataProvider encode_provider
     */
    public function test_encode(array $inputs)
    {
        $object = $inputs['object'];
        $cache_provider = new NullCacheProvider();
        $binary_codec = new BinaryCodec(
            $cache_provider,
            'abc',
            'xyz',
            $inputs['seed'] ?? '123',
            '123456'
        );
        $this->assertSame(
            $inputs['encoded'],
            bin2hex($binary_codec->encode($object))
        );
    }

    /**
     * Data provider decode.
     * @return array
     */
    public function decode_provider(): array
    {
        return [
            [
                [
                    'expected_class' => MultiCursor::class,
                    'encoded' => '54234cc3310015882e4e4cd73b3364c81e2eabf9df23fa3e3f3efa8cd1c08a908e2b74656954746e4f516e6b7151545a5263567867344951654e4377647137524b38536a7245442f6b7258467750502f75526971706153306d32746436644243667469585564664a793454674c766b4c592b344e3673413d3d',
                ], // decode a cursor
            ],
            [
                [
                    'expected_class' => NullStream::class,
                    'encoded' => '54cff06d96bc9321077363dc82180438dd6455ba921072701ff1c6d15b8c3bb4132b74656954746e4f516e6b7151545a5263567867344951654e4377647137524b38536a7245442f6b7258474a672b486c4b3473474f32696b38344e2b4b6b2f35',
                ], // decode a stream
            ],
            [
                [
                    'expected_class' => MultiCursor::class,
                    'seed' => '456',
                    'encoded' => '54fe06f8f14e35c235e8f7178f07aa7faf2f87ca98090e86ba3c7864bf2015a19142536e6f495977716b5a6b5154433872426a4f377943676e6b70537744784a4c47743758703239596b746578566339545068684f7535693945323833787a515057686e4741623643667a5945393963506935395150773d3d',
                ], // decode a cursor with seed
            ],
            [
                [
                    'expected_class' => MultiCursor::class,
                    'seed' => '123bar',
                    'encoded' => '54fcf54352bcf92139bbbc0346fde7e6c6de2c438a19737993d966121028385b726f573872716e69576a64776763544d386c7a79455a4e764b6a444161755a5257524d70384a5037327a724e2b345876466858333653305a4659686445716e4d5377615776464f6c654453513679636d653165776753773d3d',
                ], // decode a cursor with salt
            ],
            [
                [
                    'expected_class' => GlobalPositionCursor::class,
                    'seed' => '123bar',
                    'encoded' => '546be8d93f5613f379141511a59fb5d48b10e8ca800a77dbb07e8cfc4b1d8febe26f573872716e69576a64776763544d386c7a79455a4e764b6a444161755a5257524d70384a5037327a724e5335497a6d74366a7058552f6f2b5850706a6a374b31784f2f617367794e6b4e34324a45504a334b4771526958596a4a39316e2f6d79334b4e777668785470444d48484b33525a6d524531366f474268482b396358484b424f79556c454b4c4e4e524e7466436d71684d773d3d',
                ], // decode a cursor could generate trailing null bytes, this is a real error from production.
            ],
            [
                [
                    'encoded' => '',
                    'exception_msg' => 'Unable to decode with Codec(BinaryCodec): Signature size does not match: expected(32), actual(0)',
                ], // exception, empty input.
            ],
            [
                [
                    'encoded' => '543132333435363738393031323334353637383930313233343536373839303132',
                    'exception_msg' => 'Unable to decode with Codec(BinaryCodec): Encrypt byte string missing',
                ], // exception, encoded string "T12345678901234567890123456789012" 32 bytes, but missing encryption.
            ],
            [
                [
                    'encoded' => '54313233343536373839303132333435363738393031323334353637383930313233',
                    'exception_msg' => 'Unable to decode with Codec(BinaryCodec): Invalid signature, expected: hRynpeLUvOkIztLFZs4e9vHMGSH8tMJwNTy8gfLjtZw., actual: MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI.',
                ], // exception, encoded string "T123456789012345678901234567890123", invalid signature.
            ],
            [
                [
                    'encoded' => '54b3b06844b2d793905d93a5265ad801da5edbefa0f48f497dab4bf448db6e5c66504b32336d53594d70716d4c726661635a70355163773d3d',
                    'exception_msg' => 'Unable to decode with Codec(BinaryCodec): Unable to parse the json template.',
                ], // exception, same string, but with a correct signature, but broken json, this should not happen in any case.
            ],
            [
                [
                    'encoded' => '5499ad7381fbadb6f325dbc85846b3696a677ed6e031bfe425a347ae6024cd49ce626546767466377544536c776f4a2f335976796f2b4b4b4a69744b385453744d777669307931654e726e413d',
                    'exception_msg' => 'Unable to decode with Codec(BinaryCodec): Invalid stream array: {"_type":"bar"}',
                ], // exception, same string, but with a correct signature, but invalid template, this should not happen in any case.
            ],
            [
                [
                    'encoded' => '542f8139b4c1eb3db44202fed2b2670a8da78e54060b308a7f58c58e110622d2f356672b4e696a346c7975666f673361714134556473673d3d',
                    'seed' => '123bar',
                    'exception_msg' => 'Unable to decode with Codec(BinaryCodec): Invalid deflated cursor: VC+BObTB6z20QgL+0rJnCo2njlQGCzCKf1jFjhEGItLzVmcrTmlqNGx5dWZvZzNhcUE0VWRzZz09 for 123456',
                ],
            ],
        ];
    }

    /**
     * Test decode method.
     * @param array $inputs Test inputs.
     * @dataProvider decode_provider
     */
    public function test_decode(array $inputs)
    {
        $encoded = hex2bin($inputs['encoded']);
        $cache_provider = new NullCacheProvider();
        $binary_codec = new BinaryCodec(
            $cache_provider,
            'abc',
            'xyz',
            $inputs['seed'] ?? '123',
            '123456'
        );
        try {
            $object = $binary_codec->decode($encoded);
        } catch (InvalidTemplateException $e) {
            $this->assertSame($inputs['exception_msg'], $e->getMessage());
            return;
        }
        $this->assertSame($inputs['expected_class'], get_class($object));
    }
}
