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

namespace Tumblr\StreamBuilder\StreamCursors;

use Tumblr\StreamBuilder\StreamBuilder;
use function reset;
use function sprintf;
use function trim;
use function array_map;
use function array_merge;
use function is_null;

/**
 * Helper to decode/encode StreamCursors.
 */
final class StreamCursorSerializer
{
    /**
     * Gets the encoded combined cursor from the $stream_result.
     * @param StreamCursor|null $cursor The StreamCursor
     * @param string|null $user_id Current user's id, used for encrypt seed.
     * @param string|null $context Context.
     * @return string|null encoded combined cursor
     */
    public static function encodeCursor(?StreamCursor $cursor, ?string $user_id, ?string $context = null): ?string
    {
        if ($cursor === null) {
            return null;
        }
        $cache_provider = StreamBuilder::getDependencyBag()->getCacheProvider();
        [$secrets, $encrypt_keys, $encrypt_seeds] = self::getCursorSecrets();
        return StreamCursor::encode(
            $cursor,
            reset($secrets),
            reset($encrypt_keys),
            self::encryptSeed($user_id, reset($encrypt_seeds)),
            $cache_provider,
            StreamCursor::DEFAULT_CACHE_SIZE_THRESHOLD,
            $context,
            $user_id
        );
    }

    /**
     * @param string|null $user_id User id
     * @param string $encrypt_iv_salt Encrypt salt
     * @return string
     */
    private static function encryptSeed(?string $user_id, string $encrypt_iv_salt): string
    {
        return sprintf(
            '%s%s',
            $user_id ?? \Tumblr\StreamBuilder\Interfaces\User::ANONYMIZED_USER_ID,
            $encrypt_iv_salt
        );
    }

    /**
     * @param string|null $cursor_string Cursor string.
     * @param string|null $user_id Current user's id, used for encrypt seed.
     * @param string|null $context In which context, it's decoding the cursor.
     * @return StreamCursor|null Decoded cursor.
     */
    public static function decodeCursor(
        ?string $cursor_string,
        ?string $user_id,
        ?string $context = null
    ): ?StreamCursor {
        if (empty(trim($cursor_string))) {
            return null;
        }
        $cache_provider = StreamBuilder::getDependencyBag()->getCacheProvider();
        [$secrets, $encrypt_keys, $encrypt_salts] = self::getCursorSecrets();
        $encrypt_seeds = array_map(
            (fn ($a) => self::encryptSeed($user_id, $a)),
            $encrypt_salts
        );
        $logout_seeds = array_map(
            (fn ($a) => self::encryptSeed(null, $a)),
            $encrypt_salts
        );
        $encrypt_seeds = array_merge($encrypt_seeds, $logout_seeds);
        try {
            $cursor = StreamCursor::decode(
                $cursor_string,
                $secrets,
                $encrypt_keys,
                $user_id,
                $encrypt_seeds,
                $cache_provider
            );
        } catch (\Exception $e) {
            $log = StreamBuilder::getDependencyBag()->getLog();
            $log->exception($e, $context);
            return null;
        }
        return $cursor;
    }

    /**
     * Get current context's cursor secrets.
     * @return array [$acceptable_secrets, $encrypt_keys, $encrpt_salts]
     */
    public static function getCursorSecrets(): array
    {
        $creds = StreamBuilder::getDependencyBag()->getCreds();

        // @todo rename this from "dashboard" to something more generic,
        // it doesn't really have to do with the dashboard anymore
        $secret_main = $creds->get('DASHBOARD_STREAM_CURSOR_SECRET');
        $secret_alternate = $creds->getOptional('DASHBOARD_STREAM_CURSOR_SECRET_ALTERNATE');
        $acceptable_secrets = [$secret_main];
        if (!is_null($secret_alternate)) {
            $acceptable_secrets[] = $secret_alternate;
        }

        // @todo likewise, "search" here doesn't have to do with search anymore, so let's rename it
        $search_encrypt_key = $creds->get('SEARCH_STREAM_CURSOR_ENCRYPT_KEY');
        $dashboard_encrypt_key = $creds->get('DASHBOARD_STREAM_CURSOR_ENCRYPT_KEY');

        $search_encrypt_iv_salt = $creds->get('SEARCH_STREAM_CURSOR_IV_SALT');
        $dashboard_encrypt_iv_salt = $creds->get('DASHBOARD_STREAM_CURSOR_IV_SALT');

        return [
            $acceptable_secrets,
            [$dashboard_encrypt_key, $search_encrypt_key],
            [$dashboard_encrypt_iv_salt, $search_encrypt_iv_salt],
        ];
    }
}
