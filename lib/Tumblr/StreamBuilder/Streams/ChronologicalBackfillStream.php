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
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use const Tumblr\StreamBuilder\SECONDS_PER_HOUR;

/**
 * A Chronological Stream with prepending backfill results on first page depending on main stream results
 */
final class ChronologicalBackfillStream extends Stream
{
    /**
     * @var Stream
     */
    private $main_stream;

    /**
     * @var Stream|null
     */
    private $backfill_stream;

    /**
     * @var int
     */
    private $backfill_ts_minimum;

    /**
     * @param Stream $main_stream The main stream,its stream result impacts the backfill behavior
     * @param Stream $backfill_stream The stream for backfill
     * @param int $backfill_ts_minimum Minimum backfill triggering timestamp gap
     * @param string $identity The identity of this stream.
     */
    public function __construct(
        Stream $main_stream,
        Stream $backfill_stream,
        int $backfill_ts_minimum,
        string $identity
    ) {
        parent::__construct($identity);
        $this->main_stream = $main_stream;
        $this->backfill_stream = $backfill_stream;
        $this->backfill_ts_minimum = $backfill_ts_minimum;
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
        /** @var MultiCursor $cursor */
        if (is_null($cursor)) {
            $cursor = new MultiCursor([]);
            $is_first_page = true;
        } else {
            $is_first_page = false;
        }

        $final_result = [];
        $main_result =
            $this->main_stream->enumerate($count, $cursor->cursor_for_stream($this->main_stream), $tracer, $option);

        $needs_backfill = $this->should_backfill($is_first_page, $main_result, $option);

        if ($needs_backfill) {
            $result = $this->backfill_stream->enumerate(
                $count,
                $cursor->cursor_for_stream($this->backfill_stream),
                $tracer,
                $option
            );
            foreach ($result->get_elements() as $element) {
                $final_result[] = new DerivedStreamElement(
                    $element,
                    $this->get_identity(),
                    $cursor->combine_from($element)
                );
            }
        }

        foreach ($main_result->get_elements() as $element) {
            $final_result[] = new DerivedStreamElement(
                $element,
                $this->get_identity(),
                $cursor->combine_from($element)
            );
        }
        return new StreamResult(count($final_result) <= 0, $final_result);
    }

    /**
     * The backfill strategy could be further modulized
     * @param bool $is_first_page If in the first page
     * @param StreamResult $main_result The stream result from the main stream
     * @param EnumerationOptions|null $option The option for enumeration
     * @return bool
     */
    private function should_backfill(bool $is_first_page, StreamResult $main_result, ?EnumerationOptions $option): bool
    {
        if (!$is_first_page || $main_result->get_size() > 0) {
            return false;
        }

        if ($option && $option->has_time_range()) {
            $enumeration_gap_in_seconds = $option->get_time_range_in_seconds();
            // No backfill if enumeration time range is too short
            return $enumeration_gap_in_seconds >= $this->backfill_ts_minimum;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'main' => $this->main_stream->to_template(),
            'backfill' => $this->backfill_stream->to_template(),
            'backfill_ts_gap' => $this->backfill_ts_minimum,
        ];
    }
    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        return new self(
            $context->deserialize_required_property('main'),
            $context->deserialize_required_property('backfill'),
            $context->get_optional_property('backfill_ts_gap', SECONDS_PER_HOUR),
            $context->get_current_identity()
        );
    }

    /**
     * @inheritDoc
     */
    public function can_enumerate_with_time_range(): bool
    {
        return true;
    }
}
