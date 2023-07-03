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

namespace Automattic\MyAwesomeReader\StreamBuilder\Trending\Streams;

use Automattic\MyAwesomeReader\StreamBuilder\Trending\Cursors\TrendingTopicStreamCursor;
use Automattic\MyAwesomeReader\StreamBuilder\Trending\Sources\TrendingSource;
use Automattic\MyAwesomeReader\StreamBuilder\Trending\StreamElements\TrendingTopicStreamElement;
use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Trending topic stream.
 */
class TrendingTopicStream extends Stream
{
    /**
     * The constructor
     * @param string $identity The identity of the stream.
     */
    public function __construct(string $identity)
    {
        parent::__construct($identity);
    }

    /** @inheritDoc */
    protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        if (!$cursor instanceof TrendingTopicStreamCursor) {
            $cursor = new TrendingTopicStreamCursor(0);
        }
        $offset = $cursor->getOffset();
        $topics = array_slice((new TrendingSource())->getTrendingPosts(), $offset, $count);
        $elements = [];
        foreach ($topics as $topic) {
            $elements[] = new TrendingTopicStreamElement(
                $topic,
                $this->get_identity(),
                new TrendingTopicStreamCursor(++$offset)
            );
        }
        return new StreamResult(true, $elements);
    }

    // phpcs:ignore
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_current_identity());
    }
}
