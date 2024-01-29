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
use Tumblr\StreamBuilder\Exceptions\InvalidStreamArrayException;
use Tumblr\StreamBuilder\Exceptions\InvalidTemplateException;
use Tumblr\StreamBuilder\Exceptions\MissingCacheException;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\Templatable;

/**
 * Class CacheCodec
 * Supports encode and decode a templatable object by leveraging cache.
 */
final class CacheCodec extends Codec
{
    /**
     * Cache content serialized/deserialized by doing json_encode/json_decode
     * @var string
     */
    public const SERIALIZATION_TYPE_JSON = 'JSON';

    /**
     * Cache content serialized/deserialized by doing serialize/unserialize
     * @var string
     */
    public const SERIALIZATION_TYPE_PHP_OBJECT = 'PHP_OBJECT';

    /**
     * Cache type config of the cache provider, @see @CacheProvider for more details.
     * Basically this tells your the cached object group.
     * @example if we are caching a cursor, we should use CacheProvider::OBJECT_TYPE_CURSOR
     * @var int
     */
    private $cache_type;

    /**
     * The serialization type of this CacheCodec, see the type constants defined above.
     * @var string
     */
    private $serialization_type;

    /**
     * CacheCodec constructor.
     * @param CacheProvider $cache_provider The cache provider that supports cache fetching for this codec.
     * @param int $cache_type The object type in cache provider.
     * @param string $serialization_type Supports JSON and PHP_OBJECT atm, default to use PHP_OBJECT, it's faster.
     */
    public function __construct(
        CacheProvider $cache_provider,
        int $cache_type,
        string $serialization_type = self::SERIALIZATION_TYPE_JSON
    ) {
        $this->cache_type = $cache_type;
        $this->serialization_type = $serialization_type;
        parent::__construct($cache_provider);
    }

    /**
     * @inheritDoc
     */
    public function encode(Templatable $obj): string
    {
        switch ($this->serialization_type) {
            case self::SERIALIZATION_TYPE_JSON:
                $serialized = Helpers::json_encode($obj->to_template());
                break;
            case self::SERIALIZATION_TYPE_PHP_OBJECT:
                $serialized = serialize($obj);
                break;
            default:
                throw new \InvalidArgumentException(
                    sprintf('Unsupported serialization type: %s', $this->serialization_type)
                );
        }
        $encoded = sprintf('%s%s', self::CACHE_PREFIX, Helpers::get_uuid());
        $this->cache_provider->set($this->cache_type, $encoded, $serialized);
        return $encoded;
    }

    /**
     * @inheritDoc
     */
    public function decode(string $encoded): Templatable
    {
        $cached = $this->cache_provider->get($this->cache_type, $encoded);
        if (empty($cached)) {
            throw new MissingCacheException($this->cache_type, $encoded);
        }

        // there used to be a bug on memcache client, add an extra encoding call here to make sure.
        $cached = mb_convert_encoding($cached, 'UTF-8', 'ISO-8859-1');

        switch ($this->serialization_type) {
            case self::SERIALIZATION_TYPE_JSON:
                try {
                    $template = Helpers::json_decode($cached);
                    return StreamSerializer::from_template(new StreamContext($template, [], $this->cache_provider));
                } catch (\JsonException $e) {
                    throw new InvalidTemplateException(
                        'Unable to parse the json template.',
                        $this,
                        InvalidTemplateException::TYPE_INVALID_JSON
                    );
                } catch (InvalidStreamArrayException $e) {
                    throw new InvalidTemplateException(
                        $e->getMessage(),
                        $this,
                        InvalidTemplateException::TYPE_INVALID_TEMPLATE
                    );
                }
                break;
            case self::SERIALIZATION_TYPE_PHP_OBJECT:
                // unserialize will throw E_NOTICE/E_WARNING, but we are not going to catch them here.
                return unserialize($cached);
            default:
                throw new \InvalidArgumentException(
                    sprintf('Unsupported serialization type: %s', $this->serialization_type)
                );
        }
    }
}
