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

namespace Tumblr\StreamBuilder\FencepostRanking;

use Tumblr\StreamBuilder\CacheProvider;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamSerializer;

/**
 * FencepostProvider that uses a CacheProvider for "persistence".
 */
final class CacheFencepostProvider extends FencepostProvider
{
    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * @var int Default cached value TTL in seconds.
     */
    private int $ttl_seconds;

    /**
     * CacheFencepostProvider constructor.
     * @param CacheProvider $cache Provider of cache
     * @param int $ttl_seconds Default cached value TTL in seconds.
     */
    public function __construct(CacheProvider $cache, int $ttl_seconds = 0)
    {
        $this->cache = $cache;
        $this->ttl_seconds = $ttl_seconds;
    }

    /**
     * Get the cache key used for a fence.
     * @param string $fence_id The id of the fence.
     * @return string The cache key.
     */
    private static function cachekey_fence(string $fence_id): string
    {
        return sprintf('f_%s', sha1($fence_id));
    }

    /**
     * Get the cache key used for a single fencepost.
     * @param string $fence_id The id of the fence.
     * @param int $timestamp_ms The timestamp of the fencepost.
     * @return string The cache key.
     */
    private static function cachekey_fencepost(string $fence_id, int $timestamp_ms): string
    {
        return sprintf('fp_%s_%d', sha1($fence_id), $timestamp_ms);
    }

    /**
     * @inheritDoc
     */
    public function get_latest_timestamp(string $fence_id)
    {
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'cache', 'action' => 'get_ts']);
        $val = $this->cache->get(
            CacheProvider::OBJECT_TYPE_FENCEPOST_ID,
            self::cachekey_fence($fence_id)
        );
        if (is_null($val)) {
            return null;
        }
        return intval($val);
    }

    /**
     * @inheritDoc
     */
    public function set_latest_timestamp(string $fence_id, int $timestamp_ms)
    {
        if ($timestamp_ms < 0) {
            throw new \InvalidArgumentException('Timestamp must be non-negative');
        }
        $this->cache->set(
            CacheProvider::OBJECT_TYPE_FENCEPOST_ID,
            self::cachekey_fence($fence_id),
            $timestamp_ms,
            $this->ttl_seconds
        );
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'cache', 'action' => 'set_ts']);
    }

    /**
     * @inheritDoc
     */
    public function set_fencepost_epoch(string $user_id, int $timestamp_ms)
    {
        $this->cache->set(
            CacheProvider::OBJECT_TYPE_FENCEPOST_EPOCH,
            self::cachekey_fence($user_id),
            $timestamp_ms,
            $this->ttl_seconds
        );
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'cache', 'action' => 'set_epoch']);
    }

    /**
     * @inheritDoc
     */
    public function delete_fencepost_epoch(string $user_id)
    {
        $this->cache->delete(
            CacheProvider::OBJECT_TYPE_FENCEPOST_EPOCH,
            self::cachekey_fence($user_id)
        );
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'cache', 'action' => 'delete_epoch']);
    }

    /**
     * @inheritDoc
     */
    public function get_fencepost_epoch(string $user_id)
    {
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'cache', 'action' => 'get_epoch']);
        $val = $this->cache->get(
            CacheProvider::OBJECT_TYPE_FENCEPOST_EPOCH,
            self::cachekey_fence($user_id)
        );
        if (is_null($val)) {
            return null;
        }
        return intval($val);
    }

    /**
     * @inheritDoc
     */
    public function get_fencepost(string $fence_id, int $timestamp_ms)
    {
        if ($timestamp_ms < 0) {
            return null;
        }
        $fp_json = $this->cache->get(
            CacheProvider::OBJECT_TYPE_FENCEPOST,
            self::cachekey_fencepost($fence_id, $timestamp_ms)
        );
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'cache', 'action' => 'get_fp']);
        if (is_null($fp_json)) {
            return null;
        }
        $fp_template = Helpers::json_decode($fp_json);
        $ctx = new StreamContext($fp_template, [], $this->cache, sprintf('fence(%s)/%d', $fence_id, $timestamp_ms));
        return StreamSerializer::from_template($ctx);
    }

    /**
     * @inheritDoc
     */
    public function set_fencepost(string $fence_id, int $timestamp_ms, Fencepost $fencepost)
    {
        if ($timestamp_ms < 0) {
            throw new \InvalidArgumentException('Timestamp must be non-negative');
        }
        $fp_json = Helpers::json_encode($fencepost->to_template());
        $this->cache->set(
            CacheProvider::OBJECT_TYPE_FENCEPOST,
            self::cachekey_fencepost($fence_id, $timestamp_ms),
            $fp_json,
            $this->ttl_seconds
        );
        StreamBuilder::getDependencyBag()->getLog()
            ->superRateTick('fencepost_ops', ['op' => 'cache', 'action' => 'set_fp']);
    }

}
