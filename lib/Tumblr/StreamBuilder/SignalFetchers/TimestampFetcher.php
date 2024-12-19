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

namespace Tumblr\StreamBuilder\SignalFetchers;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\ChronologicalStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Signal fetcher that fetches timestamps of ChronologicalStreamElement instances.
 */
final class TimestampFetcher extends SignalFetcher
{
    /** @const string */
    public const SIGNAL_KEY = 'timestamp_ms';
    /**
     * @inheritDoc
     */
    protected function fetch_inner(array $stream_elements, ?StreamTracer $tracer = null): SignalBundle
    {
        $builder = new SignalBundleBuilder();
        foreach ($stream_elements as $stream_element) {
            /** @var StreamElement $stream_element */
            $orig = $stream_element->get_original_element();
            if ($orig instanceof ChronologicalStreamElement) {
                $builder->with_signal_for_element($stream_element, self::SIGNAL_KEY, $orig->get_timestamp_ms());
            }
        }
        return $builder->build();
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [ '_type' => get_class($this) ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        return new self($context->get_current_identity());
    }
}
