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

namespace Tumblr\StreamBuilder\StreamCursors;

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\Codec\BinaryCodec;
use Tumblr\StreamBuilder\Codec\CacheCodec;
use Tumblr\StreamBuilder\Codec\Codec;
use Tumblr\StreamBuilder\Exceptions\InappropriateTemplateException;
use Tumblr\StreamBuilder\Exceptions\InvalidCursorStringException;
use Tumblr\StreamBuilder\Exceptions\InvalidTemplateException;
use Tumblr\StreamBuilder\Exceptions\MissingCacheException;
use Tumblr\StreamBuilder\Exceptions\UncombinableCursorException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\Templatable;

/**
 * Cursors are monadic containers of opaque stream state used for pagination.
 * They are combinable with other cursors of the same type.
 */
abstract class StreamCursor extends Templatable
{
    /**
     * MAX Cursor size defined as 1k, record error while cursor size exceeds.
     */
    public const DEFAULT_CACHE_SIZE_THRESHOLD = 1800;

    /**
     * Prefix for an uuid of a cursor cache key.
     */
    public const CACHE_KEY_PREFIX = 'ckey_';

    /**
     * Signing algorithm when use the json codec.
     * @var string
     */
    public const JSON_CODEC_SIGN_ALGO = 'md5';

    /**
     * Test if this cursor can be combined with the provided cursor.
     * @param StreamCursor|null $other The other cursor.
     * @return bool True, iff the cursors can combine.
     */
    final public function can_combine_with(StreamCursor $other = null): bool
    {
        return is_null($other) || $this->_can_combine_with($other);
    }

    /**
     * Combine two cursors, yielding the cursor representing their aggregate position.
     * @param StreamCursor|null $other The cursor with which to combine this cursor.
     * @return StreamCursor The combined cursor, representing the state where
     * the elements represented by both cursors have fallen before the pagination epoch.
     * @throws UncombinableCursorException If the provided cursor cannot be combined
     * with this cursor.
     */
    final public function combine_with(StreamCursor $other = null): self
    {
        if (is_null($other)) {
            return $this;
        } elseif ($this->can_combine_with($other)) {
            return $this->_combine_with($other);
        } else {
            throw new UncombinableCursorException($this, $other);
        }
    }

    /**
     * Test if this cursor can be combined with the provided cursor.
     * @param StreamCursor $other The other cursor.
     * @return bool True, iff the cursors can combine.
     */
    abstract protected function _can_combine_with(StreamCursor $other): bool;

    /**
     * Combine two cursors, yielding the cursor representing their aggregate position.
     * The result, when provided to the source stream, should somehow indicate that both
     * elements have been consumed.
     * @param StreamCursor $other The cursor with which to combine, already validated
     * via can_combine_with.
     * @return StreamCursor The combined cursor.
     */
    abstract protected function _combine_with(StreamCursor $other): self;

    /**
     * Override __toString() to allow a cursor to decide how it will react when it is treated like a string
     * Concrete implementations are forced to be done in to_string() method
     * @return string The string representation.
     */
    final public function __toString(): string
    {
        return $this->to_string();
    }

    /**
     * @return string A string representation of this cursor, for logging purposes. The
     * representation should be human-readable and not necessarily unique, etc, etc.
     */
    abstract protected function to_string(): string;

    /**
     * @param array<StreamCursor|null> $cursors An array of StreamCursor instances
     * @return StreamCursor|null The result of combining them all.
     * @throws UncombinableCursorException If some pair of cursors in the array are
     * not combinable.
     */
    public static function combine_all(array $cursors)
    {
        $base = null;
        foreach ($cursors as $cursor) {
            /** @var StreamCursor $base */
            if (is_null($base)) {
                $base = $cursor;
            } else {
                $base = $base->combine_with($cursor);
            }
        }
        return $base;
    }

    /**
     * Encodes a StreamCursor object, by using a default BinaryCodec, if length exceeds threshold, instead use a
     * CacheCodec, it's not super efficient, since if so, we wasted the JsonCodec encode operation, but not easy
     * to tell if exceeds before we do any encoding.
     *
     * @param StreamCursor $cursor The StreamCursor need to be encoded.
     * @param string $secret The secret used to generate a signature.
     * @param string $encrypt_key The encrypt key used to encrypt if this encode logic encrypts. @see BinaryCodec
     * @param string $initial_vector_seed The initial vector seed for BinaryCodec.
     * @param CacheProvider|null $cache_provider The cache provider to use. If null, caching cursors is disabled.
     * @param int $cache_size_threshold The maximum size allowed for an uncached cursor. Anything larger
     * will be cached (unless no cache provider is supplied)
     * @param string|null $context Context the encode happened.
     * @param string|null $current_user_id The current user id. Optional, used for throwing exception info.
     * @return string The encoded cursor string.
     */
    public static function encode(
        StreamCursor $cursor,
        string $secret,
        string $encrypt_key,
        string $initial_vector_seed = '',
        CacheProvider $cache_provider = null,
        int $cache_size_threshold = self::DEFAULT_CACHE_SIZE_THRESHOLD,
        ?string $context = null,
        ?string $current_user_id = null
    ): string {
        $binary_codec = new BinaryCodec(
            $cache_provider,
            $secret,
            $encrypt_key,
            $initial_vector_seed,
            $current_user_id
        );

        $encoded = $binary_codec->encode($cursor);
        $encoded = Helpers::base64UrlEncode($encoded);

        $cursor_size = strlen($encoded);
        $context = $context ?? 'unknown';

        if (($cache_provider instanceof CacheProvider) && ($cursor_size > $cache_size_threshold)) {
            StreamBuilder::getDependencyBag()->getLog()
                ->rateTick('cursor_ops', 'cached');
            $cache_codec = new CacheCodec(
                $cache_provider,
                CacheProvider::OBJECT_TYPE_CURSOR,
                CacheCodec::SERIALIZATION_TYPE_JSON
            );
            $encoded = $cache_codec->encode($cursor);
            // base64 url encode cache encoded as well, to line up with binary codec encoded logic.
            $encoded = Helpers::base64UrlEncode($encoded);

            // This is an implementation detail, but the CacheCodec does not base64 encode the payload before
            // writing to cache, so the original cursor_size is misleading. Base64 encoding can increase the
            // payload size by 33%.
            $cursor_size = strlen(Helpers::json_encode($cursor->to_template()));
        }

        StreamBuilder::getDependencyBag()->getLog()
            ->histogramTick('cursor_size', $context, ($cursor_size / 1000.0));

        return $encoded;
    }

    /**
     * The reverse logic of the static::encode method, it tries to figure out the appropriate codec first, and
     * then decode to a StreamCursor object.
     *
     * @param string $cursor_string The encoded cursor string.
     * @param string[] $secrets The secrets allowed to validate signature.
     * @param string[] $encrypt_keys The encrypt keys used to decrypt from a @example BinaryCodec.
     * @param string|null $current_user_id The current user id. Optional, used for throwing exception info.
     * @param string[] $encrypt_seeds The initial seeds for BinaryCodec
     * @param CacheProvider|null $cache_provider The cache provider to use. If null, caching cursors is disabled.
     *
     * @return StreamCursor|null
     * @throws InappropriateTemplateException When all things are valid, but the decode object is not a StreamCursor.
     * @throws \InvalidArgumentException If a cached cursor is detected, but no cache provider is configured,
     * or there's no main secret provide.
     * @throws InvalidTemplateException When we failed to decode the cursor, an exception type will be provided.
     * @throws MissingCacheException When it's encoded with CacheCodec, but when we load, it's missing.
     * @throws InvalidCursorStringException When we can not decode the cursor string with specific reason.
     */
    public static function decode(
        string $cursor_string,
        array $secrets,
        array $encrypt_keys,
        ?string $current_user_id = null,
        array $encrypt_seeds = [],
        CacheProvider $cache_provider = null
    ): ?self {
        if (empty(trim($cursor_string))) {
            return null;
        }
        // always apply a base64_url_decode
        $decoded_cursor_string = Helpers::base64UrlDecode($cursor_string);
        // at this point, $cursor_string should not be empty
        if (empty(trim($decoded_cursor_string))) {
            return null;
        }
        $secret = $secrets[0] ?? false;
        $exception = null;
        if (empty($encrypt_seeds)) {
            $encrypt_seeds = [""];
        }
        foreach ($encrypt_keys as $encrypt_key) {
            foreach ($encrypt_seeds as $encrypt_seed) {
                try {
                    $codec = Codec::detect_codec(
                        $decoded_cursor_string,
                        $secret,
                        $encrypt_key,
                        $encrypt_seed,
                        $cache_provider,
                        CacheProvider::OBJECT_TYPE_CURSOR,
                        $current_user_id
                    );
                    $cursor = $codec->decode($decoded_cursor_string);
                    if ($cursor instanceof self) {
                        return $cursor;
                    }
                } catch (\Exception $e) {
                    $exception = $e;
                }
            }
        }
        if ($exception) {
            throw $exception;
        } else {
            throw new InvalidCursorStringException($cursor_string);
        }
    }
}
