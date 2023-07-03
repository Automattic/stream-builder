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

namespace Automattic\MyAwesomeReader\StreamBuilder;

use Automattic\MyAwesomeReader\StreamBuilder\InterfaceImplementations\ContextProvider;
use Automattic\MyAwesomeReader\StreamBuilder\InterfaceImplementations\Credentials;
use Automattic\MyAwesomeReader\StreamBuilder\InterfaceImplementations\StreamBuilderLog;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\TransientCacheProvider;

/**
 * The dependency provider for StreamBuilder.
 */
class DependencyProvider
{
    /**
     * Creates a dependency bag for StreamBuilder.
     * @return DependencyBag The dependency bag
     */
    public static function createDependencyBag(): DependencyBag
    {
        return new DependencyBag(
            new StreamBuilderLog(),
            new TransientCacheProvider(),
            new Credentials(),
            new ContextProvider()
        );
    }

    /**
     * Load the StreamBuilder framework. Should only be called once.
     * @return void
     */
    public static function loadStreamBuilder(): void
    {
        $dependency_bag = self::createDependencyBag();
        StreamBuilder::init($dependency_bag);
    }
}
