<?php
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

namespace Tumblr\StreamBuilder\Codec;

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Exceptions\InvalidTemplateException;
use Tumblr\StreamBuilder\Exceptions\NoCodecAvailableException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\NullCacheProvider;
use Tumblr\StreamBuilder\Templatable;

/**
 * Class Codec
 * This codec class is an abstraction layer of all encoding/decoding procedures in StreamBuilder.
 * It offers two major interfaces to covert a Templatable object to an encoded string and vice versa.
 * If you implement a customized Codec, you should specify the logic to detect what codec to use in the
 * detect_codec method.
 */
abstract class Codec
{
    /**
     * Prefix of a BinaryCodec encode output.
     * WARNING: If you are going to change this, make sure you fully understand the backwards/forwards compatibility
     * of the binary encode string.
     * @var string
     */
    public const BINARY_PREFIX = 'T';

    /**
     * Prefix of a CacheCodec encode output.
     * WARNING: If you are going to change this, make sure you fully understand the backwards/forwards compatibility
     * of the binary encode string.
     * @var string
     */
    public const CACHE_PREFIX = 'ckey_';

    /**
     * The length of a signature length, currently it's sha256 and 32 bytes.
     * @var int
     */
    public const SIGNATURE_LENGTH = 32;

    /**
     * The min length of a JSON encoded output. Smallest should be "{}".
     * @var int
     */
    public const JSON_ENCODE_MIN_LENGTH = 2;

    /**
     * Cache provider that supports cache fetching for this codec.
     * @var CacheProvider
     */
    protected $cache_provider;

    /**
     * Codec constructor.
     * @param CacheProvider|null $cache_provider The cache provider used to deserialize a template.
     * @see @StreamContext, there are stuff from stream are cached during the to_template process.
     */
    public function __construct(?CacheProvider $cache_provider = null)
    {
        $this->cache_provider = $cache_provider;
    }

    /**
     * Encode a templatable object.
     * @param Templatable $obj The object to encode.
     * @return string The encoded bytes string.
     */
    abstract public function encode(Templatable $obj): string;

    /**
     * Decode an encoded templatable object.
     * @param string $encoded The encoded bytes string to be decoded.
     * @throws InvalidTemplateException When it failed to decode.
     * @return Templatable The decoded templatable object.
     */
    abstract public function decode(string $encoded): Templatable;

    /**
     * Detect and return the codec used to encode a given bytes string.
     * @param string $encoded The encoded bytes string to be decoded.
     * @param string $sign_secret The secret we use to sign an encoded object.
     * @param string $encrypt_key The key we use to encrypt an object.
     * @param string $initial_vector_seed The initial vector seed for BinaryCodec, @see the constructor of @BinaryCodec
     * @param CacheProvider|null $cache_provider The cache provider used to detect.
     * @param int $cache_type The type of cached object, must be given along with $cache_provider. To fully
     * locate where the cached object is stored. @see @CacheProvider
     * @param string|null $current_user_id The current user id. Optional, used for throwing exception info.
     * @throws NoCodecAvailableException When no Code is available for the input bytes string.
     * @return Codec The corresponding type of codec that should be used to decode.
     */
    final public static function detect_codec(
        string $encoded,
        string $sign_secret,
        string $encrypt_key,
        string $initial_vector_seed,
        ?CacheProvider $cache_provider = null,
        int $cache_type = 0,
        ?string $current_user_id = null
    ): self {
        $len = strlen($encoded);
        if ($len === (self::SIGNATURE_LENGTH + strlen(self::CACHE_PREFIX))
            && strpos($encoded, self::CACHE_PREFIX) === 0) {
            // If cache provider is null, fallback to a null cache provider, which would provide nothing.
            $cache_provider = $cache_provider ?: new NullCacheProvider();
            // NOTE: it returns a cache codec with a default serialization type of PHP_OBJECT.
            return new CacheCodec($cache_provider, $cache_type);
        }

        if ($len >= (self::SIGNATURE_LENGTH + strlen(self::BINARY_PREFIX))
            && strpos($encoded, self::BINARY_PREFIX) === 0) {
            return new BinaryCodec(
                $cache_provider,
                $sign_secret,
                $encrypt_key,
                $initial_vector_seed,
                $current_user_id
            );
        }
        throw new NoCodecAvailableException(Helpers::base64UrlEncode($encoded));
    }
}
