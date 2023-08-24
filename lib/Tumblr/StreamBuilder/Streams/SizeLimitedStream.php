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

namespace Tumblr\StreamBuilder\Streams;

use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\SizeLimitedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function sprintf;
use function is_null;
use function array_slice;
use function array_map;
use function count;
use function get_class;
use function method_exists;

/**
 * Use this stream to limit the overall elements can be enumerated from a stream.
 */
class SizeLimitedStream extends Stream
{
    /**
     * The stream to be size limited with.
     * @var Stream
     */
    private $stream;

    /**
     * The max elements can be enumerated from this stream, within an active session, across multiple pages.
     * @var int
     */
    private $limit;

    /**
     * SizeLimitedStream constructor.
     * @param Stream $stream The stream to be size limited.
     * @param int $limit The max element size of this stream, across multiple pages!
     * @param string $identity The identity string of the stream.
     * @throws \InvalidArgumentException If input size is less than 1.
     */
    public function __construct(Stream $stream, int $limit, string $identity)
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException(sprintf('Size should be at least 1, while inputs %d', $limit));
        }
        $this->stream = $stream;
        $this->limit = $limit;
        parent::__construct($identity);
    }

    /**
     * @inheritDoc
     */
    protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        if (is_null($cursor)) {
            $cursor = new SizeLimitedStreamCursor(null, 0);
        } elseif (!($cursor instanceof SizeLimitedStreamCursor)) {
            throw new InappropriateCursorException($this, $cursor);
        }

        $balance = $this->limit - $cursor->get_current_size();
        if ($balance <= 0) {
            // Yes, you already reached the max size elements from this stream, you will always get empty result from now on.
            return StreamResult::create_empty_result();
        }
        if ($balance < $count) {
            $count = $balance;
        }
        $result = $this->stream->enumerate($count, $cursor->get_inner_cursor(), $tracer, $option);
        $elements = $result->get_elements();
        $selected_elements = array_slice($elements, 0, $balance);

        $derived_elements = array_map(function (StreamElement $e) use ($cursor) {
            $new_cursor = new SizeLimitedStreamCursor(
                $e->get_cursor(),
                $cursor->get_current_size() + 1
            );
            return new DerivedStreamElement($e, $this->get_identity(), $new_cursor);
        }, $selected_elements);

        // if this page size is greater than stream's limit, we don't want to keep paginating.
        $is_exhausted = count($derived_elements) < $count || count($derived_elements) >= $this->limit;
        return new StreamResult($is_exhausted, $derived_elements);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'stream' => $this->stream->to_template(),
            'limit' => $this->limit,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $limit = $context->get_required_property('limit');
        // if limit is not provide, will throw \TypeError on purpose
        $stream = $context->deserialize_required_property('stream');
        return new self($stream, $limit, $context->get_current_identity());
    }

    /**
     * @param string $query_string Query string.
     * @return void
     */
    public function setQueryString(string $query_string)
    {
        if (method_exists($this->stream, 'setQueryString')) {
            $this->stream->setQueryString($query_string);
        } else {
            StreamBuilder::getDependencyBag()
                ->getLog()
                ->warning('Trying to fetch posts from stream without setting the query string');
        }
    }
}
