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
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use const Tumblr\StreamBuilder\QUERY_SORT_ASC;
use const Tumblr\StreamBuilder\QUERY_SORT_DESC;

/**
 * Mixes streams so they maintain a chronological order across the streams.
 */
final class ChronologicalStreamMixer extends StreamMixer
{
    /**
     * @const string ascending
     */
    public const ASCENDING = QUERY_SORT_ASC;

    /**
     * @const string descending
     */
    public const DESCENDING = QUERY_SORT_DESC;

    /**
     * @var Stream[]
     */
    private $streams;

    /**
     * @var string technically, asc or desc
     */
    private $chronological_order;

    /**
     * ChronologicalStreamMixer constructor.
     * @param StreamInjector $injector The injector used during enumeration.
     * @param string $identity String identifying the stream.
     * @param Stream[] $chronological_streams Streams array for mixing.
     * @param string $chronological_order Stream ordering rule: asc or desc.
     */
    public function __construct(StreamInjector $injector, string $identity, array $chronological_streams, string $chronological_order)
    {
        parent::__construct($injector, $identity);
        $this->streams = $chronological_streams;
        $this->chronological_order = $chronological_order;
    }

    /**
     * @inheritDoc
     */
    protected function mix(
        int $count,
        MultiCursor $cursor,
        ?StreamTracer $tracer,
        InjectionPlan $injection_plan,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $res = [];
        foreach ($this->streams as $index => $stream) {
            try {
                $stream_results = $stream->enumerate($count, $cursor->cursor_for_stream($stream), $tracer, $option);
            } catch (\Exception $e) {
                // keep enumerating other streams and log exception
                $log = StreamBuilder::getDependencyBag()->getLog();
                $log->exception($e, $this->get_identity());
                $stream_results = new StreamResult(true, []);
            }
            foreach ($stream_results->get_elements() as $stream_element) {
                $original_element = $stream_element->get_original_element();
                if ($original_element instanceof ChronologicalStreamElement) {
                    $res[] = $stream_element;
                }
            }
        }

        // magic pre-fetcher!
        StreamElement::pre_fetch_all($res);

        usort($res, function (StreamElement $el_1, StreamElement $el_2) {
            $el_1 = $el_1->get_original_element();
            $el_2 = $el_2->get_original_element();

            /** @var ChronologicalStreamElement $el_1 */
            $el_1_ts = $el_1->get_timestamp_ms();
            /** @var ChronologicalStreamElement $el_2 */
            $el_2_ts = $el_2->get_timestamp_ms();

            if ($this->chronological_order === self::DESCENDING) {
                return $el_2_ts <=> $el_1_ts;
            } elseif ($this->chronological_order === self::ASCENDING) {
                return $el_1_ts <=> $el_2_ts;
            } else {
                throw new \UnexpectedValueException('Unexpected value for sorting option. Options: asc or desc');
            }
        });
        $elements = array_slice($res, 0, $count);
        return new StreamResult(count($elements) < $count, $elements);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'stream_injector' => $this->injector->to_template(),
            'stream_array' => array_map(function ($stream) {
                /** @var Stream $stream */
                return $stream->to_template();
            }, $this->streams),
            'order' => $this->chronological_order,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        /** @var StreamInjector $injector */
        $injector = $context->deserialize_required_property('stream_injector');
        $stream_array = $context->get_optional_property('stream_array', []);
        // Override template defined order or use default descending.
        $chronological_order = $context->get_optional_property('order', self::DESCENDING);
        $chronological_streams = [];

        foreach ($stream_array as $i => $stream) {
            $stream_context = $context->derive($stream, sprintf('stream_array/%d', $i));
            $chronological_streams[] = StreamSerializer::from_template($stream_context);
        }
        return new self($injector, $context->get_current_identity(), $chronological_streams, $chronological_order);
    }

    /**
     * @inheritDoc
     */
    protected function can_enumerate_with_time_range(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function can_enumerate(): bool
    {
        if (!parent::can_enumerate()) {
            return false;
        }
        foreach ($this->streams as $stream) {
            if ($stream->can_enumerate()) {
                // we need at least one inner stream to enumerate a mix.
                return true;
            }
        }
        return false;
    }
}
