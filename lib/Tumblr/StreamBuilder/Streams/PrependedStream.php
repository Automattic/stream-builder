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
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Stream which takes exactly two streams. First stream should have a limit count.
 * So the enumeration will act like it consumes a certain count of elements of the first stream, then will consume the second stream.
 * An easy way to prepend one stream with another, if you want to show a banner at the "top" or something like that.
 *
 * If it sounds like a {@see ConcatenatedStream}, you're not wrong: but there's a very subtle difference. The first
 * Stream is always prepended (when a null cursor is provided, i.e. on the first page) and the number of elements
 * returned from the first Stream does not affect the {$count} requested from the second Stream! So if you ask for
 * count=10, and we prepend two things, you will get 12 elements back. Prepending is special and allowed to violate the
 * rules for {$count}.
 */
class PrependedStream extends Stream
{
    /**
     * Arbitrary value, put in just so that if you accidentally provide an infinite stream it will eventually end
     * @const int
     */
    public const DEFAULT_LIMIT = 10;

    /**
     * The stream to be prepended.
     * @var Stream
     */
    private $before;

    /**
     * The "after" stream.
     * @var Stream
     */
    private $after;

    /**
     * Maximum number of elements to take from the "before" stream.
     * @var int
     */
    private $limit;

    /**
     * PrependedStream constructor.
     * @param Stream $before Prepend stream, will only take {$limit} elements for enumeration.
     * @param int $limit Count limit for {$before} enumeration elements.
     * Will stop enumerate {$before} once meet the {$limit} or meet the end of {$before}.
     * Default provided so that if an infinite stream is supplied it will stop eventually
     * @param Stream $after Prepended stream, will do normal enumeration after consumes prepend stream.
     * @param string $identity Identity string.
     * @throws \InvalidArgumentException When parameters not valid.
     * @return PrependedStream
     */
    public function __construct(Stream $before, ?int $limit, Stream $after, string $identity)
    {
        if (is_null($limit)) {
            $limit = self::DEFAULT_LIMIT;
        }
        if ($limit < 0) {
            throw new \InvalidArgumentException(sprintf('Invalid limit argument : %s, please use a valid one.', $limit));
        }
        $this->before = $before;
        $this->after = $after;
        $this->limit = $limit;
        parent::__construct($identity);
    }

    /**
     * @inheritDoc
     */
    protected function _enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $results = $this->after->enumerate($count, $cursor, $tracer, $option);
        if (empty($cursor)) {
            try {
                $prepend_result = $this->before->enumerate($this->limit, null, $tracer, $option);
            } catch (\Throwable $t) {
                // for individual stream failure we will degrade service by returning empty result
                // instead of throwing exception that would cause whole request to fail or skip other stream's enumeration.
                $log = StreamBuilder::getDependencyBag()->getLog();
                $log->exception($t, $this->get_identity());
                $prepend_result = new StreamResult(true, []);
            }
            $prepend_result = array_map(function ($e) {
                return new DerivedStreamElement($e, $this->get_identity(), null);
            }, $prepend_result->get_elements());
            $results = StreamResult::prepend($prepend_result, $results);
        }
        return $results;
    }


    /**
     * Convert an object to a template.
     * @return array A serialized representative template for an object.
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'before' => $this->before->to_template(),
            'after' => $this->after->to_template(),
            'limit' => $this->limit,
        ];
    }

    /**
     * Use this method to create a stream object from a template array.
     * @param StreamContext $context The context stores the stream template and other necessary data.
     * @return PrependedStream The Prepend stream object corresponding to input.
     */
    public static function from_template(StreamContext $context): self
    {
        /** @var Stream $before */
        $before = $context->deserialize_required_property('before');
        /** @var Stream $after */
        $after = $context->deserialize_required_property('after');
        return new self($before, $context->get_optional_property('limit'), $after, $context->get_current_identity());
    }

    /**
     * @inheritDoc
     */
    public function estimate_count(): ?int
    {
        return $this->after->estimate_count();
    }

    /**
     * @inheritDoc
     */
    protected function can_enumerate(): bool
    {
        return parent::can_enumerate()
            && ($this->before->can_enumerate() || $this->after->can_enumerate());
    }
}
