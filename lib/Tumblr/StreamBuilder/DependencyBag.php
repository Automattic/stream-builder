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

namespace Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\Interfaces\ContextProvider;
use Tumblr\StreamBuilder\Interfaces\Credentials;
use Tumblr\StreamBuilder\Interfaces\Log;

/**
 * Dependency bag to hold all dependencies needed for StreamBuilder to operate.
 */
class DependencyBag
{
    /**
     * @var Log The log object.
     */
    private Log $log;

    /**
     * @var CacheProvider The cache provider
     */
    private CacheProvider $cache_provider;

    /**
     * @var Credentials The credentials object.
     */
    private Credentials $creds;

    /**
     * @var ContextProvider The context provider configuration.
     */
    private ContextProvider $context_provider;

    /**
     * @param Log $log The log object.
     * @param CacheProvider $cache_provider The cache provider.
     * @param Credentials $creds The credentials object.
     * @param ContextProvider $context_provider The context provider configuration.
     */
    public function __construct(
        Log $log,
        CacheProvider $cache_provider,
        Credentials $creds,
        ContextProvider $context_provider
    ) {
        $this->log = $log;
        $this->cache_provider = $cache_provider;
        $this->creds = $creds;
        $this->context_provider = $context_provider;
    }

    /**
     * @return Log The log object.
     */
    public function getLog(): Log
    {
        return $this->log;
    }

    /**
     * @return CacheProvider
     */
    public function getCacheProvider(): CacheProvider
    {
        return $this->cache_provider;
    }

    /**
     * @return Credentials The credentials object.
     */
    public function getCreds(): Credentials
    {
        return $this->creds;
    }

    /**
     * @return array<string, string>
     */
    public function getContextProvider(): array
    {
        return $this->context_provider->getContextProvider();
    }

    /**
     * @return string|null The config dir, if it exists.
     */
    public function getConfigDir(): ?string
    {
        return $this->context_provider->getConfigDir();
    }

    /**
     * @return string The path to the base directory of your app.
     */
    public function getBaseDir(): string
    {
        return $this->context_provider->getBaseDir();
    }
}
