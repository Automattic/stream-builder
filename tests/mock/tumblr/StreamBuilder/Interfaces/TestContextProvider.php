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

namespace Test\Mock\Tumblr\StreamBuilder\Interfaces;

use Tumblr\StreamBuilder\Interfaces\ContextProvider;

/**
 * A context provider for testing.
 */
class TestContextProvider implements ContextProvider
{
    /**
     * @inheritDoc
     */
    public function getContextProvider(): array
    {
        return [
            'examples' => '../../lib/Tumblr/StreamBuilder/Templates',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getConfigDir(): ?string
    {
        return CONFIG_DIR;
    }

    /**
     * @inheritDoc
     */
    public function getBaseDir(): string
    {
        return BASE_PATH;
    }
}
