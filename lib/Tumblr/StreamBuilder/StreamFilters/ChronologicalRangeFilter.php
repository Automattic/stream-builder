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

namespace Tumblr\StreamBuilder\StreamFilters;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Filter which releases chronological elements falling outside the specified range of timestamps.
 */
final class ChronologicalRangeFilter extends StreamElementFilter
{
    /** @var int|null */
    private $max_timestamp_ms_inclusive;
    /** @var int|null */
    private $min_timestamp_ms_exclusive;
    /** @var bool */
    private $release_non_chrono;

    /**
     * @param string $identity String identifying this element in the context of a stream topology.
     * @param int|null $max_timestamp_ms_inclusive The upper bound timestamp in milliseconds, inclusive,
     *     to retain. If null, there is no upper bound.
     * @param int|null $min_timestamp_ms_exclusive The lower bound timestamp, in milliseconds, exclusive,
     *     to retain. If null, there is no lower bound.
     * @param bool $release_non_chrono If true, also release non-chronological elements.
     * @throws \InvalidArgumentException If neither parameter is specified.
     */
    public function __construct(
        string $identity,
        int $max_timestamp_ms_inclusive = null,
        int $min_timestamp_ms_exclusive = null,
        bool $release_non_chrono = false
    ) {
        if (is_null($max_timestamp_ms_inclusive) && is_null($min_timestamp_ms_exclusive)) {
            throw new \InvalidArgumentException('At least one of before or after should be specified');
        }
        if (
            (!is_null($max_timestamp_ms_inclusive)) &&
            (!is_null($min_timestamp_ms_exclusive)) &&
            ($max_timestamp_ms_inclusive <= $min_timestamp_ms_exclusive)
        ) {
            throw new \InvalidArgumentException('If both timestamps are provided, max must be strictly greater than min');
        }
        parent::__construct($identity);
        $this->max_timestamp_ms_inclusive = $max_timestamp_ms_inclusive;
        $this->min_timestamp_ms_exclusive = $min_timestamp_ms_exclusive;
        $this->release_non_chrono = $release_non_chrono;
    }

    /**
     * @inheritDoc
     */
    protected function pre_fetch(array $elements)
    {
        StreamElement::pre_fetch_all($elements);
    }

    /**
     * @inheritDoc
     */
    protected function should_release(StreamElement $e): bool
    {
        $orig = $e->get_original_element();
        if ($orig instanceof ChronologicalStreamElement) {
            $ts = $orig->get_timestamp_ms();
            $valid_before = (is_null($this->max_timestamp_ms_inclusive) || ($ts <= $this->max_timestamp_ms_inclusive));
            $valid_after = (is_null($this->min_timestamp_ms_exclusive) || ($ts > $this->min_timestamp_ms_exclusive));
            $should_retain = ($valid_before && $valid_after);
            return (!$should_retain);
        } else {
            return $this->release_non_chrono;
        }
    }

    /**
     * @inheritDoc
     */
    public function get_cache_key()
    {
        // probably shouldn't cache this, too many variations.
        return null;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'max_timestamp_ms_inclusive' => $this->max_timestamp_ms_inclusive,
            'min_timestamp_ms_exclusive' => $this->min_timestamp_ms_exclusive,
            'release_non_chrono' => $this->release_non_chrono,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_current_identity(),
            $context->get_optional_property('max_timestamp_ms_inclusive'),
            $context->get_optional_property('min_timestamp_ms_exclusive'),
            $context->get_optional_property('release_non_chrono', false)
        );
    }
}
