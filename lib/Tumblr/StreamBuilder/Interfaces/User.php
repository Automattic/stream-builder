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

namespace Tumblr\StreamBuilder\Interfaces;

/**
 * User interface to avoid coupling to a specific user implementation.
 * It's meant to be implemented to your User model, or create an anonymous class if that's not possible
 */
interface User
{
    /**
     * @var string Use this as the `user_id` in your log calls to anonymize it.
     */
    public const ANONYMIZED_USER_ID = '-1';

    /**
     * Get the user id. If it doesn't exist, an anonymous/unknown id must be returned.
     * @return string the user id.
     */
    public function getUserId(): string;

    /**
     * @return bool Whether the user allow dashboard ranking (algodash).
     */
    public function isFeedRankingEnabled(): bool;
}
