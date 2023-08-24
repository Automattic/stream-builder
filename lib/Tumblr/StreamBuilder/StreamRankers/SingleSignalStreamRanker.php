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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use function usort;
use function sprintf;
use function strtolower;
use const Tumblr\StreamBuilder\QUERY_SORT_ASC;
use const Tumblr\StreamBuilder\QUERY_SORT_DESC;

/**
 * A signal-based stream ranker that ranks by a single signal, either ascending or descending.
 */
final class SingleSignalStreamRanker extends SignalFetcherStreamRanker
{
    /** @const int */
    public const ORDER_DESCENDING = QUERY_SORT_DESC;
    /** @const int */
    public const ORDER_ASCENDING = QUERY_SORT_ASC;

    /** @var string */
    private $signal_key;
    /** @var string */
    private $ordering_mode;

    /**
     * @param SignalFetcher $signal_fetcher The signal fetcher that returns the desired ranking signal.
     * @param string $signal_key The signal key to use for ranking, e.g. PostNotecountFetcher::SIGNAL_KEY
     * @param string $ordering_mode Either ::ORDER_DESCENDING or ::ORDER_ASCENDING.
     * @param string $identity The identity of this ranker.
     * @throws \InvalidArgumentException If $ordering_mode is invalid.
     */
    public function __construct(SignalFetcher $signal_fetcher, string $signal_key, string $ordering_mode, string $identity)
    {
        if ($ordering_mode != self::ORDER_DESCENDING && $ordering_mode != self::ORDER_ASCENDING) {
            throw new \InvalidArgumentException(sprintf('Unknown ordering mode %s', $ordering_mode));
        }
        parent::__construct($signal_fetcher, $identity);
        $this->signal_key = $signal_key;
        $this->ordering_mode = $ordering_mode;
    }

    /**
     * @inheritDoc
     */
    protected function rank_by_signals(array $stream_elements, SignalBundle $signals): array
    {
        usort($stream_elements, function (StreamElement $a, StreamElement $b) use ($signals) {
            $sa = $signals->get_signal_for_element($a, $this->signal_key);
            $sb = $signals->get_signal_for_element($b, $this->signal_key);
            switch ($this->ordering_mode) {
                case self::ORDER_ASCENDING:
                    return $sa <=> $sb;
                case self::ORDER_DESCENDING:
                    return $sb <=> $sa;
                default:
                    throw new \RangeException(sprintf('Invalid ordering mode %d', $this->ordering_mode));
            }
        });
        return $stream_elements;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['signal_key'] = $this->signal_key;
        $base['ordering_mode'] = $this->ordering_mode;
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        return new self(
            $context->deserialize_required_property('signal_fetcher'),
            $context->get_required_property('signal_key'),
            strtolower($context->get_required_property('ordering_mode')),
            $context->get_current_identity()
        );
    }

    /**
     * @inheritDoc
     */
    protected function pre_fetch(array $elements)
    {
        // no need to do anything
    }
}
