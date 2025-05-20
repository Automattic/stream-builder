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
use Tumblr\StreamBuilder\Exceptions\InvalidStreamArrayException;
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\ProportionalRoundRobinStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A round-robin "mixer" that mixes secondary streams in a particular sequence and proportion.
 */
final class ProportionalRoundRobinStream extends Stream
{
    /**
     * @var Stream
     */
    private $major_stream;

    /**
     * @var Stream[]
     */
    private $minor_streams;

    /**
     * Array of ordered indexes into $minor_streams which define the round-robin ordering and proportion, e.g. [ 0, 1, 0, 2 ]
     * @var int[]
     */
    private $minor_stream_order;

    /**
     * @var int
     */
    private $minor_stream_order_count;

    /**
     * @var int
     */
    private $minor_modulus;

    /**
     * @var int
     */
    private $minor_remainder;

    /**
     * @param Stream $major_stream The primary stream
     * @param Stream[] $minor_streams The ordered array of minor streams
     * @param int[] $minor_stream_order Array of indexes into $minor_streams which determine the round-robin order.
     * The same index may appear multiple times.
     * @param int $minor_modulus The modulus over which to insert elements from the minor streams.
     * @param int $minor_remainder The remainder at which to insert elements from the minor streams.
     * @param string $identity The identity of this stream.
     * @throws \InvalidArgumentException If an argument is invalid.
     * @throws TypeMismatchException If some argument contains an element of an unexpected type.
     */
    public function __construct(Stream $major_stream, array $minor_streams, array $minor_stream_order, int $minor_modulus, int $minor_remainder, string $identity)
    {
        if (empty($minor_streams)) {
            throw new \InvalidArgumentException('At least one minor stream must be provided');
        }

        foreach ($minor_streams as $minor_stream) {
            if (!($minor_stream instanceof Stream)) {
                throw new TypeMismatchException(Stream::class, $minor_stream);
            }
        }

        if (empty($minor_stream_order)) {
            throw new \InvalidArgumentException('A minor stream order must be provided');
        }

        $minor_stream_count = count($minor_streams);
        foreach ($minor_stream_order as $minor_stream_index) {
            if ($minor_stream_index < 0 || $minor_stream_index >= $minor_stream_count) {
                throw new \InvalidArgumentException(sprintf('Minor stream index %d is invalid', $minor_stream_index));
            }
        }

        if ($minor_modulus < 2) {
            throw new \InvalidArgumentException('Minor modulus must be greater than one');
        }

        if ($minor_remainder < 0 || $minor_remainder >= $minor_modulus) {
            throw new \InvalidArgumentException('Minor remainder must be less than the modulus, and not less than zero');
        }

        parent::__construct($identity);
        $this->major_stream = $major_stream;
        $this->minor_streams = $minor_streams;
        $this->minor_stream_order = $minor_stream_order;
        $this->minor_stream_order_count = count($minor_stream_order);
        $this->minor_modulus = $minor_modulus;
        $this->minor_remainder = $minor_remainder;
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
        /** @var ProportionalRoundRobinStreamCursor $cursor */
        if (is_null($cursor)) {
            $cursor = ProportionalRoundRobinStreamCursor::make_empty();
        } elseif (!($cursor instanceof ProportionalRoundRobinStreamCursor)) {
            throw new InappropriateCursorException($this, $cursor);
        }

        $begin_index = $cursor->get_begin_index();

        /** @var int[] $stream_assignment */
        /** @var int[] $minor_counts */
        $stream_assignment = [];
        $minor_counts = [];
        for ($i = 0; $i < $count; $i++) {
            $stream_index = $this->determine_stream_for_position($i + $begin_index);
            $stream_assignment[$i] = $stream_index;
            if (is_int($stream_index)) {
                // count how many elements we need from each minor stream:
                $minor_counts[$stream_index] = 1 + ($minor_counts[$stream_index] ?? 0);
            }
        }

        /** @var StreamElement[][] $minor_elements */
        $minor_elements = [];
        foreach ($minor_counts as $stream_index => $ct) {
            $stream = $this->minor_streams[$stream_index];
            try {
                $minor_stream_result =
                    $stream->enumerate($ct, $cursor->get_minor_stream_cursor($stream_index), $tracer, $option);
            } catch (\Exception $e) {
                $log = StreamBuilder::getDependencyBag()->getLog();
                $log->exception($e, $this->get_identity());
                // keep enumerating the stream despite the exception
                $minor_stream_result = new StreamResult(true, []);
            }
            $minor_elements[$stream_index] = $minor_stream_result->get_elements();
        }

        $major_result = $this->major_stream->enumerate($count, $cursor->get_major_stream_cursor(), $tracer, $option);
        $major_elements = $major_result->get_elements();

        $is_exhaustive = false;
        $output_elements = [];
        foreach ($stream_assignment as $pos => $stream_index) {
            if (is_int($stream_index) && ($elem = array_shift($minor_elements[$stream_index]))) {
                $output_elements[$pos] = new DerivedStreamElement($elem, $this->get_identity(), $cursor->derive_minor($elem, $stream_index, $pos));
                continue;
            }
            if ($elem = array_shift($major_elements)) {
                $output_elements[$pos] = new DerivedStreamElement($elem, $this->get_identity(), $cursor->derive_major($elem, $pos));
                continue;
            }
            // hmm, no more elements. Return a exhaustive result.
            $is_exhaustive = true;
            break;
        }

        return new StreamResult($is_exhaustive, $output_elements);
    }

    /**
     * Determine which stream to use at the given position.
     * @param int $position_index The index of the position for which to determine a stream.
     * @return int|null The index of the minor stream to use at this position, or null
     * if this position should be occupied by a major stream.
     */
    private function determine_stream_for_position(int $position_index)
    {
        if (($position_index % $this->minor_modulus) == $this->minor_remainder) {
            // use minor stream:
            $order_index = (floor($position_index / $this->minor_modulus) % $this->minor_stream_order_count);
            return $this->minor_stream_order[$order_index];
        } else {
            // use major stream.
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'major' => $this->major_stream->to_template(),
            'minor_modulus' => $this->minor_modulus,
            'minor_remainder' => $this->minor_remainder,
            'minors' => array_map(function (Stream $minor) {
                return $minor->to_template();
            }, $this->minor_streams),
            'minor_stream_order' => $this->minor_stream_order,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $major = StreamSerializer::from_template($context->derive($template['major'] ?? null, 'major'));

        $minors = [];
        foreach ($template['minors'] as $i => $minor_template) {
            $minors[$i] = StreamSerializer::from_template($context->derive($minor_template, sprintf('minors/%d', $i)));
        }

        if ((!isset($template['minor_modulus'])) || (!isset($template['minor_remainder']))) {
            throw new InvalidStreamArrayException($template);
        }

        return new self(
            $major,
            $minors,
            $template['minor_stream_order'] ?? [],
            intval($template['minor_modulus']),
            intval($template['minor_remainder']),
            $context->get_current_identity()
        );
    }

    /**
     * @inheritDoc
     */
    protected function can_enumerate(): bool
    {
        return parent::can_enumerate() && $this->major_stream->can_enumerate();
    }
}
