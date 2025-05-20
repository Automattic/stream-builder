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

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A SignalFetcher that composes a number of other signal fetchers.
 */
final class CompositeSignalFetcher extends SignalFetcher
{
    /** @var SignalFetcher[] */
    private $fetchers;

    /**
     * @param SignalFetcher[] $fetchers The fetchers to compose.
     * Later fetchers have precedence over earlier ones if they use the same keys.
     * @param string $identity The identity of this composed fetcher.
     * @throws TypeMismatchException If some element of $fetchers is not a SignalFetcher.
     */
    public function __construct(array $fetchers, string $identity)
    {
        parent::__construct($identity);
        foreach ($fetchers as $fetcher) {
            if (!($fetcher instanceof SignalFetcher)) {
                throw new TypeMismatchException(SignalFetcher::class, $fetcher);
            }
        }
        $this->fetchers = $fetchers;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function fetch_inner(array $stream_elements, ?StreamTracer $tracer = null): SignalBundle
    {
        $bundles = [];
        foreach ($this->fetchers as $fetcher) {
            $bundles[] = $fetcher->fetch($stream_elements, $tracer);
        }
        return SignalBundle::combine_all($bundles);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'fetchers' => array_map(function (SignalFetcher $sf) {
                return $sf->to_template();
            }, $this->fetchers),
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $fetcher_templates = $template['fetchers'] ?? [];
        $fetchers = [];
        foreach ($fetcher_templates as $i => $fetcher_template) {
            $fetchers[] = StreamSerializer::from_template($context->derive($fetcher_template, sprintf('fetchers/%d', $i)));
        }
        return new self($fetchers, $context->get_current_identity());
    }
}
