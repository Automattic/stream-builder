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
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\Exceptions\InvalidStreamArrayException;
use Tumblr\StreamBuilder\Exceptions\InvalidTemplateException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\Templatable;

/**
 * Class BinaryCodec
 * A codec encodes templatable object into bytes string.
 */
final class BinaryCodec extends Codec
{
    /**
     * The OpenSSL encryption cipher to use.
     * @var string
     */
    private const CIPHER = 'AES-256-CBC';

    /**
     * The secret used to sign.
     * @var string
     */
    private $sign_secret;

    /**
     * A key used to encrypt.
     * @var string
     */
    private $encrypt_key;

    /**
     * Initial vector for encryption, with size of 16 bytes. A 16 byte IV is required for AES-256-CBC.
     *
     * @var string
     */
    private $initial_vector;

    /**
     * @var string|null The current user id. Optional, used for throwing exception info.
     */
    private ?string $current_user_id;

    /**
     * BinaryCodec constructor.
     * @param CacheProvider|null $cache_provider The cache provider used to deserialize a template.
     * @param string $sign_secret The secret used to sign the encoded object.
     * @param string $encrypt_key The key used to encrypt.
     * @param string $initial_vector_seed We want to use a dynamic initial vector for each BinaryCodec, so that it
     * creates more work for hackers to decrypt it. You can pass any level of uniqueness ids to this param, so that
     * the dynamism is attached to that level. You can pass a random string for it, but keep it consistent for each
     * request, otherwise, it would break.
     * @param string|null $current_user_id The current user id. Optional, used for throwing exception info.
     * @throws \InvalidArgumentException If the initial vector is being incorrectly generated from the seed.
     */
    public function __construct(
        ?CacheProvider $cache_provider,
        string $sign_secret,
        string $encrypt_key,
        string $initial_vector_seed = '',
        ?string $current_user_id = null
    ) {
        $this->sign_secret = $sign_secret;
        $this->encrypt_key = $encrypt_key;
        $this->current_user_id = $current_user_id;

        // The use of hex2bin(md5(...)) ensures that the initial vector is always 16 bytes long.
        // Caution should be taken when changing this, as it will break existing encoded data.
        $this->initial_vector = hex2bin(md5($initial_vector_seed));

        // If you're really certain that you want to change how the initial vector is generated,
        // here's an extra check to make sure you're generating an IV with the correct length.
        if (strlen($this->initial_vector) !== openssl_cipher_iv_length(self::CIPHER)) {
            throw new \InvalidArgumentException(sprintf('IV is of incorrect length for cipher %s', self::CIPHER));
        }

        parent::__construct($cache_provider);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function encode(Templatable $obj): string
    {
        $json = Helpers::json_encode($obj->to_template());
        $compressed = gzdeflate($json);
        $encrypted = openssl_encrypt($compressed, self::CIPHER, $this->encrypt_key, 0, $this->initial_vector);
        $signature = $this->compute_signature($encrypted);

        return sprintf('%s%s%s', self::BINARY_PREFIX, $signature, $encrypted);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function decode(string $encoded): Templatable
    {
        $actual_signature = substr($encoded, strlen(self::BINARY_PREFIX), self::SIGNATURE_LENGTH);
        if (strlen($actual_signature) !== self::SIGNATURE_LENGTH) {
            throw new InvalidTemplateException(sprintf(
                'Signature size does not match: expected(%d), actual(%d)',
                self::SIGNATURE_LENGTH,
                strlen($actual_signature)
            ), $this, InvalidTemplateException::TYPE_MISSING_COMPONENT);
        }

        $encrypted = substr($encoded, strlen(self::BINARY_PREFIX) + self::SIGNATURE_LENGTH);
        if ($encrypted === false || strlen($encrypted) === 0) {
            throw new InvalidTemplateException(
                'Encrypt byte string missing',
                $this,
                InvalidTemplateException::TYPE_MISSING_COMPONENT
            );
        }

        $expected_signature = $this->compute_signature($encrypted);
        if (!hash_equals($expected_signature, $actual_signature)) {
            throw new InvalidTemplateException(
                sprintf(
                    'Invalid signature, expected: %s, actual: %s',
                    Helpers::base64UrlEncode($expected_signature),
                    Helpers::base64UrlEncode($actual_signature)
                ),
                $this,
                InvalidTemplateException::TYPE_SIGNATURE_MISMATCH
            );
        }

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $this->encrypt_key, 0, $this->initial_vector);
        try {
            $json = gzinflate($decrypted);
        } catch (\Throwable $t) {
            throw new InvalidTemplateException(
                sprintf(
                    'Invalid deflated cursor: %s for %s',
                    base64_encode($encoded),
                    $this->current_user_id
                ),
                $this,
                InvalidTemplateException::TYPE_INVALID_DEFLATION
            );
        }

        try {
            $template = Helpers::json_decode($json);
        } catch (\JsonException $e) {
            // NOTE: this should never happen, with a valid signature but broken json blob means our encoding
            // method is wrong!
            throw new InvalidTemplateException(
                'Unable to parse the json template.',
                $this,
                InvalidTemplateException::TYPE_INVALID_JSON
            );
        }

        try {
            $object = StreamSerializer::from_template(new StreamContext($template, [], $this->cache_provider));
        } catch (InvalidStreamArrayException $e) {
            // NOTE: this should never happen either, with a valid signature but broken template means our encoding
            // method is wrong!
            throw new InvalidTemplateException(
                $e->getMessage(),
                $this,
                InvalidTemplateException::TYPE_INVALID_TEMPLATE
            );
        }

        return $object;
    }

    /**
     * A shared method between the encode and decode method to compute signature.
     * @param string $data The string data to compute a signature.
     * @return string The signature.
     */
    private function compute_signature(string $data): string
    {
        return hash('SHA256', $this->sign_secret . $data, true);
    }
}
