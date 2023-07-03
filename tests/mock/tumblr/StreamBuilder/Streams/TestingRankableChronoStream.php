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

namespace Test\Tumblr\StreamBuilder\Streams;

use Test\Tumblr\StreamBuilder\StreamCursors\TestingChronoCursor;
use Test\Tumblr\StreamBuilder\StreamElements\TestingRankableChronoStreamElement;
use Tumblr\StreamBuilder\EnumerationOptions\EnumerationOptions;
use Tumblr\StreamBuilder\Exceptions\InappropriateCursorException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Stream for testing fencepost ranking.
 */
final class TestingRankableChronoStream extends Stream
{
    /** @var TestingRankableChronoStreamElement[] */
    private $elements;

    /**
     * @param string $identity Identity of this stream
     * @param TestingRankableChronoStreamElement[] $elements The elements in the stream.
     */
    public function __construct($identity, array $elements)
    {
        parent::__construct($identity);
        usort($elements, function (TestingRankableChronoStreamElement $a, TestingRankableChronoStreamElement $b) {
            return $b->get_timestamp_ms() <=> $a->get_timestamp_ms();
        });
        $this->elements = $elements;
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
            $offset = 0;
        } elseif ($cursor instanceof TestingChronoCursor) {
            $cursor_ts = $cursor->get_timestamp_ms();
            // first element with ts < cursor_ts;
            $offset = count($this->elements);
            foreach ($this->elements as $i => $elem) {
                if ($elem->get_timestamp_ms() < $cursor_ts) {
                    $offset = $i;
                    break;
                }
            }
        } else {
            throw new InappropriateCursorException($this, $cursor);
        }
        $elems = array_slice($this->elements, $offset, $count + 1);

        // Check Option for range filter
        if ($option) {
            $filtered_elem = [];
            foreach ($elems as $el) {
                $ts = $el->get_timestamp_ms();
                if (!$option->is_valid_ts($ts)) {
                    // Out of range
                    continue;
                }
                $filtered_elem[] = $el;
            }
        } else {
            $filtered_elem = $elems;
        }

        return new StreamResult(count($filtered_elem) <= $count, array_slice($filtered_elem, 0, $count));
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'elements' => array_map(function (TestingRankableChronoStreamElement $e) {
                return $e->to_template();
            }, $this->elements),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_current_identity(),
            $context->deserialize_array_property('elements')
        );
    }

    /**
     * @inheritDoc
     */
    protected function can_enumerate_with_time_range(): bool
    {
        return true;
    }
}
