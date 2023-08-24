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

namespace Tests\Unit\Tumblr\StreamBuilder;

use function gettype;
use function is_resource;
use function is_object;
use function get_object_vars;
use function is_array;
use function array_keys;
use function sort;

class TestUtils
{
    /**
     * Perform a strict === comparison on each property of object $a and object $b, asserting each is strictly equal,
     * recursively.
     *
     * @param mixed $a Left side object to compare
     * @param mixed $b Right side object to compare
     * @param string $level String representation of the tree, e.g. "obj->foo->bar". Used for error messages.
     * @return void
     *
     * @throws \InvalidArgumentException On trying to compare resources
     */
    public static function assertSameRecursively($a, $b, string $level = ''): void
    {
        $type_a = gettype($a);
        $type_b = gettype($b);

        \PHPUnit\Framework\TestCase::assertSame(
            $type_a,
            $type_b,
            "Data type of A{$level} ($type_a) matches B{$level} ($type_b)"
        );

        if (is_resource($a) || is_resource($b)) {
            throw new \InvalidArgumentException('Resources cannot be compared');
        }

        if (is_object($a)) {
            $props_a = array_keys(get_object_vars($a));
            $props_b = array_keys(get_object_vars($b));

            sort($props_a);
            sort($props_b);

            \PHPUnit\Framework\TestCase::assertSame(
                $props_a,
                $props_b,
                "Object properties of A{$level} match those of B{$level}"
            );

            foreach ($props_a as $prop) {
                self::assertSameRecursively($a->$prop, $b->$prop, $level . '->' . $prop);
            }

            return;
        }

        if (is_array($a)) {
            $keys_a = array_keys($a);
            $keys_b = array_keys($b);

            sort($keys_a);
            sort($keys_b);

            \PHPUnit\Framework\TestCase::assertSame(
                $keys_a,
                $keys_b,
                "Array keys of A{$level} match those of B{$level}"
            );

            foreach ($keys_a as $prop) {
                self::assertSameRecursively($a[$prop], $b[$prop], $level . '["' . $prop . '"]');
            }

            return;
        }

        \PHPUnit\Framework\TestCase::assertSame($a, $b, "Type/value of A{$level} ($a) matches B{$level} ($b)");
    }
}
