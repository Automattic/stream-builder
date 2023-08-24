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

namespace Tumblr\StreamBuilder;

use function is_array;
use function sprintf;

/**
 * Context passed along in deserialize process, stores necessary meta data for stream generation.
 * Template array description can be found in Templatable.
 * Data stores in $meta could be:
 *  1. User $user related, keyed with 'user', the user who is requesting the stream.
 *  2. Data about the current HTTP request.
 *  3. etc...
 */
final class StreamContext
{
    /** @var string A special meta that could differentiate components */
    public const COMPONENT_NAME = '_component';

    /** @var string A special meta that used to mark component been skipped. */
    public const SKIP_COMPONENT_META = 'skip_component';

    /**
     * @var array|null
     */
    private $template;

    /**
     * @var array
     */
    private $meta;

    /**
     * @var string
     */
    private $current_identity;

    /**
     * @var CacheProvider
     */
    private $cache_provider;

    /**
     * StreamContext constructor.
     * @param array|null $template Allowed null template here, exception will be thrown in the serializer.
     * @param array $meta The meta data passed through stream deserialization, could contain stuff as described in
     * class comments.
     * @param CacheProvider|null $cache_provider The provider of caching functionality.
     * If null, a NullCacheProvider will be used.
     * @param string $current_identity The absolute directory to the current node in the stream template.
     */
    public function __construct(
        ?array $template,
        array $meta,
        ?CacheProvider $cache_provider = null,
        string $current_identity = 'unknown'
    ) {
        $this->template = $template;
        $this->current_identity = $current_identity;
        $this->meta = $meta;
        $this->cache_provider = $cache_provider ?? new NullCacheProvider();
    }

    /**
     * @return array|null The stored template, it could be null.
     */
    public function get_template(): ?array
    {
        return $this->template;
    }

    /**
     * @return string
     */
    public function get_current_identity(): string
    {
        return $this->current_identity;
    }

    /**
     * Please prefer get_meta_by_key, this should only be used to create StreamContexts for reading embedded templates
     * @return array Meta
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param string $key The key looked for, an example could be 'user'.
     * @return mixed The data object stored.
     */
    public function get_meta_by_key(string $key)
    {
        return $this->meta[$key] ?? null;
    }

    /**
     * Getter for cache provider.
     * @return CacheProvider, never null.
     */
    public function get_cache_provider(): CacheProvider
    {
        return $this->cache_provider;
    }

    /**
     * Generate a new stream context for the nested node in the template, this is mostly used
     * during the stream template deserialization process.
     * @param array|null $template The new template array to derive a StreamContext.
     * @param string $sub_identity The subdirectory of the current template array.
     * @return StreamContext The derived context, containing the provided template, a derived identity, and
     * the same meta and cache provider as this instance.
     */
    public function derive(?array $template, string $sub_identity): StreamContext
    {
        // Derive sub template info
        $derived_meta = $this->meta;
        if (isset($template[static::COMPONENT_NAME])) {
            $derived_meta[static::COMPONENT_NAME] = $template[static::COMPONENT_NAME];
        }
        return new StreamContext(
            $template,
            $derived_meta,
            $this->cache_provider,
            sprintf('%s/%s', $this->current_identity, $sub_identity)
        );
    }

    /**
     * Helper method to derive a new context from a sub-property of the current template,
     * using that property name as the sub-identity.
     * @param string $property_name The name of the property to drill into.
     * @return StreamContext
     * @throws \InvalidArgumentException If the requested property does not exist.
     */
    public function derive_property(string $property_name): StreamContext
    {
        if (!isset($this->template[$property_name])) {
            throw new \InvalidArgumentException(
                sprintf('Template does not have expected property "%s"', $property_name)
            );
        }
        return $this->derive($this->template[$property_name], $property_name);
    }

    /**
     * Get the value of a required property.
     * @param string $property_name The name of the required property to retrieve.
     * @return mixed The value of the property.
     * @throws \InvalidArgumentException If the property does not exist.
     */
    public function get_required_property(string $property_name)
    {
        if (!isset($this->template[$property_name])) {
            throw new \InvalidArgumentException(
                sprintf('Template does not have expected property "%s"', $property_name)
            );
        }
        return $this->template[$property_name];
    }

    /**
     * Get the value of an optional property.
     * @param string $property_name The name of the required property to retrieve.
     * @param mixed $default The value to return if the property does not exist.
     * @return mixed The value of the property, or the default.
     */
    public function get_optional_property(string $property_name, $default = null)
    {
        if (!isset($this->template[$property_name])) {
            return $default;
        }
        return $this->template[$property_name];
    }

    /**
     * Helper method to deserialize a required sub-property of the current template, using that property name as the
     * sub-identity.
     * @param string $property_name The name of the property to drill into.
     * @param string|null $sub_identity The name of the sub-property to use to build the identity of the element. If
     * null, uses the property name.
     * @return mixed
     */
    public function deserialize_required_property(string $property_name, ?string $sub_identity = null)
    {
        return StreamSerializer::from_template($this->derive(
            $this->get_required_property($property_name),
            $sub_identity ?? $property_name
        ));
    }

    /**
     * Helper method to deserialize an optional sub-property of the current template, using that property name as the
     * sub-identity.
     * @param string $property_name The name of the property to drill into.
     * @param mixed $default The default value to return if the property does not exist or has a null value.
     * @param null|string $sub_identity The name of the sub-property to use to build the identity of the element. If
     * null, uses the property name.
     * @return mixed
     */
    public function deserialize_optional_property(string $property_name, $default = null, ?string $sub_identity = null)
    {
        if (!isset($this->template[$property_name])) {
            return $default;
        }
        return $this->deserialize_required_property($property_name, $sub_identity);
    }

    /**
     * Helper method to deserialize the elements of an array sub-property of the current template,
     * using that property name and array index as the sub-identity.
     * @param string $property_name The name of the array-valued property to drill into.
     * @throws \InvalidArgumentException If the requested property exists, but does not have an array value.
     * @return mixed[]
     */
    public function deserialize_array_property(string $property_name)
    {
        /** @var array[] $templates */
        $templates = $this->get_optional_property($property_name, []);
        if (!is_array($templates)) {
            throw new \InvalidArgumentException(sprintf('Template does not have array property "%s"', $property_name));
        }
        $outputs = [];
        foreach ($templates as $i => $template) {
            $outputs[$i] = StreamSerializer::from_template($this->derive(
                $template,
                sprintf('%s/%s', $property_name, $i)
            ));
        }
        return $outputs;
    }
}
