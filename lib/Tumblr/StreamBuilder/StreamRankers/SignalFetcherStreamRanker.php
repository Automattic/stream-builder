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

namespace Tumblr\StreamBuilder\StreamRankers;

use Tumblr\StreamBuilder\SignalFetchers\SignalBundle;
use Tumblr\StreamBuilder\SignalFetchers\SignalFetcher;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A StreamRanker that provides its ranking method with signals obtained from the provided signal fetcher.
 */
abstract class SignalFetcherStreamRanker extends StreamRanker
{
    /** @var string */
    public const DEBUG_HEADER = 'ranking_signals';

    /** @var SignalFetcher */
    private $signal_fetcher;

    /**
     * @param SignalFetcher $signal_fetcher The signal fetcher used for retrieving signal(s).
     * @param string $identity The identity of this ranker.
     */
    public function __construct(SignalFetcher $signal_fetcher, string $identity)
    {
        parent::__construct($identity);
        $this->signal_fetcher = $signal_fetcher;
    }

    /**
     * @inheritDoc
     */
    final protected function rank_inner(array $stream_elements, ?StreamTracer $tracer = null): array
    {
        /** @var StreamElement[] $stream_elements */
        $signals = $this->signal_fetcher->fetch($stream_elements, $tracer);
        foreach ($stream_elements as $stream_element) {
            // put signals in debug info
            if (!empty($element_signals = $signals->get_signals_for_element($stream_element))) {
                $stream_element->add_debug_info(self::DEBUG_HEADER, $this->get_identity(), $element_signals);
            }
        }
        return $this->rank_by_signals($stream_elements, $signals);
    }

    /**
     * Method implemented by inheritors to rank elements using signals.
     * @param StreamElement[] $stream_elements The elements to rank.
     * @param SignalBundle $signals The signals to use for ranking.
     * @return StreamElement[] Same elements as input, reranked. It is illegal to add or remove elements during this operation.
     */
    abstract protected function rank_by_signals(array $stream_elements, SignalBundle $signals): array;

    /**
     * to_template functionality shared by inheritors.
     * @return array
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'signal_fetcher' => $this->signal_fetcher->to_template(),
        ];
    }
}
