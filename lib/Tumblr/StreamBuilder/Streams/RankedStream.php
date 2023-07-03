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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A Stream which allows reranking of elements. It only ranks within a page to avoid skipping.
 * If you need long-range reranking, see BufferedRankedStream.
 */
final class RankedStream extends WrapStream
{
    /** @var StreamRanker */
    private $ranker;

    /**
     * @param Stream $inner The stream from which to fetch elements for ranking.
     * @param StreamRanker $ranker Ranker used to order elements.
     * @param string $identity The identity of this stream.
     */
    public function __construct(Stream $inner, StreamRanker $ranker, string $identity)
    {
        parent::__construct($inner, $identity);
        $this->ranker = $ranker;
    }

    /**
     * @inheritDoc
     */
    final protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $res = $this->getInner()->enumerate($count, $cursor, $tracer, $option);
        $elems = $res->get_elements();
        try {
            $ranked_elems = $this->ranker->rank($elems, $tracer);
        } catch (\Exception $e) {
            // ranker failure been swallowed and return original sequence
            return $res;
        }
        return new StreamResult($res->is_exhaustive(), $ranked_elems);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'inner' => $this->getInner()->to_template(),
            'ranker' => $this->ranker->to_template(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        return new self(
            $context->deserialize_required_property('inner'),
            $context->deserialize_required_property('ranker'),
            $context->get_current_identity()
        );
    }

    /**
     * Getter for the inner ranker
     * @return StreamRanker The inner ranker
     */
    public function get_ranker()
    {
        return $this->ranker;
    }
}
