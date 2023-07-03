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
 * Class for configuring StreamBuilder.
 */
final class StreamBuilder
{
    /**
     * @var DependencyBag|null A singleton holding all the dependencies.
     */
    private static ?DependencyBag $dependency_bag;

    /**
     * Initialize StreamBuilder.
     * @param DependencyBag $dependency_bag The dependency bag.
     * @return void
     */
    public static function init(DependencyBag $dependency_bag): void
    {
        self::setDependencyBag($dependency_bag);
    }

    /**
     * Set the big bag o' dependencies.
     * @param DependencyBag $dependency_bag The dependency bag.
     * @return void
     * @throws \RuntimeException If the dependency bag has already been set.
     */
    private static function setDependencyBag(DependencyBag $dependency_bag): void
    {
        if (isset(self::$dependency_bag)) {
            throw new \RuntimeException('Dependency bag already set.');
        }
        self::$dependency_bag = $dependency_bag;
    }

    /**
     * Get the big bag o' dependencies.
     * @return DependencyBag The dependency bag.
     * @throws \RuntimeException If the dependency bag hasn't been set yet.
     */
    public static function getDependencyBag(): DependencyBag
    {
        if (!isset(self::$dependency_bag)) {
            throw new \RuntimeException('Dependency bag not set.');
        }
        return self::$dependency_bag;
    }
}
