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

/**
 * Static helper functions used in various places within the StreamBuilder framework.
 */
class Helpers
{
    /**
     * Static class.
     */
    private function __construct()
    {
        // Private constructor for static method.
    }

    /**
     * Base-64 encodes the input string, replacing url-unfriendly characters with safer ones.
     * Pair with {@link base64UrlDecode}
     *
     * Uses alternative base-64 alphabet defined in RFC-4648's base64url.
     *
     * @param string $input The input to encode.
     * @return string The result encoded.
     */
    public static function base64UrlEncode(string $input): string
    {
        return strtr(base64_encode($input), '+/=', '-_.');
    }

    /**
     * Base-64 decodes an input string that has been encoded using {@link base64UrlEncode}.
     *
     * Uses alternative base-64 alphabet defined in RFC-4648's base64url.
     *
     * @param string $input The input to decode.
     * @return string The result decoded.
     */
    public static function base64UrlDecode(string $input): string
    {
        return base64_decode(strtr($input, '-_.', '+/='));
    }

    /**
     * Convert a long (64 bit integer) to bytes.
     * @param int $int64 Number to convert to bytes.
     * @return string Byte-string containing bytes of the given long (always has strlen of 8)
     */
    public static function long_to_bytes($int64): string
    {
        // `J` was {@link http://php.net/manual/en/function.pack.php added to pack in 5.6.3.}
        return pack("J", $int64);
    }

    /**
     * An `idx` analogue which takes two keys (in order of preference) in order to support graceful migration from one key to another.
     * @param array $array The array to search.
     * @param string|int $key1 The preferred key to read.
     * @param string|int $key2 The secondary key to read.
     * @param mixed $default The value to return if neither key is found, default null.
     * @return mixed
     */
    public static function idx2(array $array, $key1, $key2, $default = null)
    {
        return $array[$key1] ?? $array[$key2] ?? $default;
    }

    /**
     * To get an universal unique id.
     * It's prefixed by hostname and process id, logically, at one time, one host, one process should only handle one request.
     * Thus it should be globally unique.
     * @return string
     */
    public static function get_uuid(): string
    {
        $uuid_prefix = sprintf('%s_%s_', gethostname(), getmypid());
        return md5(uniqid($uuid_prefix, true));
    }

    /**
     * Get the in-memory id of a specific object, used to build "identity" maps.
     * @param object $element The element.
     * @return string The id.
     */
    public static function memory_element_id($element): string
    {
        return sprintf('p%s', spl_object_hash($element));
    }

    /**
     * Compute an identity map for objects.
     * @param object[] $elements The elements for which to compute the map.
     * @return object[] The map of identity to element.
     */
    public static function element_identity_map(array $elements): array
    {
        $map = [];
        foreach ($elements as $element) {
            $map[self::memory_element_id($element)] = $element;
        }
        return $map;
    }

    /**
     * Verify that $before and $after have the same values (ref-equal!), just reordered.
     * @param object[] $before The array before.
     * @param object[] $after The array after.
     * @return bool True if $before and $after have the same elements, possibly reordered.
     */
    public static function verify_reordered_elements(array $before, array $after): bool
    {
        if (count($before) != count($after)) {
            return false;
        }
        $shared = array_intersect_key(self::element_identity_map($before), self::element_identity_map($after));
        return count($shared) == count($before);
    }

    /**
     * Return all elements from an array, omitting the specified element.
     * @param object[] $haystack The array of elements.
     * @param object $needle The element to omit.
     * @return object[] The haystack without the needle.
     */
    public static function omit_element(array $haystack, $needle): array
    {
        foreach (array_keys($haystack, $needle, true) as $needle_key) {
            unset($haystack[$needle_key]);
        }
        return array_values($haystack);
    }

    /**
     * To get a class name without namespace.
     * @param mixed $object Any instance object.
     * @return string
     */
    public static function get_unqualified_class_name($object): string
    {
        $class = get_class($object);
        return self::get_unqualified_class_name_from_string($class);
    }

    /**
     * To get a class name from a string without namespace.
     * @param string $class_string A string representation of a class name, eg MyNamespace/Sub/ClassName
     * @return string
     */
    public static function get_unqualified_class_name_from_string(string $class_string): string
    {
        $class_path = explode('\\', $class_string);
        return end($class_path);
    }

    /**
     * Get a millisecond-granularity timestamp.
     * @return int The current epoch timestamp measured in milliseconds.
     */
    public static function current_timestamp_ms(): int
    {
        return intval(1000.0 * microtime(true));
    }

    /**
     * A wrapper around json_encode that throws an exception on error, and applies our preferred formatting flags.
     * @param mixed $value The value to encode.
     * @return string The JSON string.
     * @throws \JsonException If the JSON is invalid.
     */
    public static function json_encode($value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A wrapper around json_decode that throws an exception on error.
     * @param string $json The JSON string to decode.
     * @return mixed The decoded JSON.
     * @throws \JsonException If the JSON is invalid.
     */
    public static function json_decode(string $json)
    {
        if (
            (!$json && !is_numeric($json)) ||
            $json === 'NULL'   // needed for backward compatibility w/ PHP5 parsing see WEAP-1545
        ) {
            return null;
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
