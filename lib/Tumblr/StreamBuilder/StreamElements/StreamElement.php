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

namespace Tumblr\StreamBuilder\StreamElements;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\Templatable;

/**
 * Things that can be enumerated from streams. Each element may have a cursor if it participates
 * in pagination. While element types can be reused across streams (e.g. Posts), it is unlikely
 * that cursors can (or should) be.
 */
abstract class StreamElement extends Templatable
{
    /**
     * The identity of the stream which created this element.
     * @var string
     */
    private $provider_identity;

    /**
     * The cursor representing the pagination state after consuming this element.
     * @var StreamCursor|null
     */
    private $cursor;

    /**
     * StreamElement constructor.
     * @param string $provider_identity The identity of the stream which created this element.
     * @param StreamCursor|null $cursor The cursor representing the pagination state after consuming this element.
     */
    public function __construct(
        string $provider_identity,
        ?StreamCursor $cursor = null
    ) {
        parent::__construct(Helpers::get_unqualified_class_name($this));
        $this->provider_identity = $provider_identity;
        $this->cursor = $cursor;
    }

    /**
     * @return string The identity of the stream that provided this element.
     */
    final public function get_provider_identity(): string
    {
        return $this->provider_identity;
    }

    /**
     * @return StreamCursor|null The cursor representing the pagination state after consuming this element.
     */
    final public function get_cursor()
    {
        return $this->cursor;
    }

    /**
     * Override this StreamElement's cursor.
     * @param StreamCursor|null $cursor The overrider cursor.
     * @return void
     */
    final public function set_cursor(?StreamCursor $cursor): void
    {
        $this->cursor = $cursor;
    }

    /**
     * To get an **unique** id for an stream element, will be used for tracing the entire lifecycle of this element,
     * including being generated, ranked, filtered, converted.
     * @return string
     */
    abstract public function get_element_id(): string;

    /**
     * @return StreamElement The original element, which may be $this. Because elements become derived while
     * passing through a series of stream-transformations (filters, mixers, etc.), they lose their type
     * information (e.g. at the end, everything will be a DerivedStreamElement). When you're rendering
     * such elements, always render their originals (which will be posts, blogs, etc.).
     */
    abstract public function get_original_element(): StreamElement;

    /**
     * @return StreamElement The parent element, which may be $this. Because elements become derived while
     * passing through a series of stream-transformations (filters, mixers, etc.), they lose their type
     * information (e.g. at the end, everything will be a DerivedStreamElement). When you're rendering
     * such elements, always render their originals (which will be posts, blogs, etc.).
     */
    abstract public function get_parent_element(): StreamElement;

    /**
     * @return string|null A key which can uniquely identify this StreamElement in a cache.
     * When null, implies non-cacheability.
     * It should somehow include the *type* of the element, to avoid collisions with elements of other types.
     * It should be as small as possible, ane not necessarily human-readable!
     * For a human-readable identifier, use __toString().
     * These keys are also used in filter states, which often "act like a cache", just client-side.
     *
     * Registry of non-base64 special characters:
     *  - `!`: PostStreamElement
     *  - `~`, `[`, `]`: BlogRecommendationStreamElement
     *  - `^`: BlogStreamElement
     *  - `.`: DeduplicatedStreamFilterState (used in **template** so it cant be used in cache keys)
     *  - `>`: DashboardScopeStreamElement
     *  - `*`: BlazedPostStreamElement
     *
     * Note that you should NEVER EVER need to "parse" a cache key. So these characters are only to ensure uniqueness,
     * not to necessarily make it easy to parse (though technically, the uniqueness constraint implies parseability).
     */
    abstract public function get_cache_key();

    /**
     * We override __toString and call the abstract to_string method, which FORCES implementors to implement
     * some form of stringification. Otherwise, we cant force everyone to do it...
     * @return string The human-readable string representation of this element, for logging purposes.
     */
    final public function __toString(): string
    {
        return $this->to_string();
    }

    /**
     * @return string A string representation of this element, for logging purposes. The
     * representation should be human-readable and not necessarily unique, etc, etc.
     */
    abstract protected function to_string(): string;

    /**
     * Sets the given field to the given value under the given header of this
     * StreamElement's debug info.
     *
     * Example usage:
     *      add_debug_info("Ross' family", "wife", "Ngoc");
     *      add_debug_info("Ross' family", "son", "Harrison");
     *      add_debug_info("Ross' family", "dog", "Bruce");
     *
     * @param string $header The category under which several bits of debug info
     * may be grouped.
     * @param string $field Describes the info being logged.
     * @param mixed $value The value of the given field to be logged.
     * @return void
     */
    abstract public function add_debug_info(string $header, string $field, $value);

    /**
     * @return string[][] The debug info for this StreamElement.
     */
    abstract public function get_debug_info(): array;

    /**
     * @inheritdoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['provider_id'] = $this->provider_identity;
        $base['cursor'] = (is_null($this->cursor) ? null : $this->cursor->to_template());
        $base['element_id'] = $this->get_element_id();
        return $base;
    }

    /**
     * Call any pre-fetchers available for the given elements.
     * This is dynamic by design, so that the core StreamBuilder
     * does not need to "know" about tumblr-specific classes' pre_fetch methods,
     * but can still find them at runtime when needed.
     * Basically it enumerates all the distinct element-classes,
     * looks for static `pre_fetch` methods, and calls them if found.
     * @param StreamElement[] $elements The elements to pre-fetch.
     * @return void
     */
    final public static function pre_fetch_all(array $elements): void
    {
        $classes = [];
        foreach ($elements as $e) {
            if ($e instanceof StreamElement) {
                $element_class = get_class($e->get_original_element());
                $classes[$element_class] = $element_class;
            }
        }
        foreach ($classes as $class) {
            if (method_exists($class, 'pre_fetch')) {
                call_user_func([$class, 'pre_fetch'], $elements);
            }
        }
    }
}
