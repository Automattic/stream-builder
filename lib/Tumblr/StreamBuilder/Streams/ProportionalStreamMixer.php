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
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\ProportionalMixture;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\StreamWeight;

/**
 * A StreamMixer which draws elements randomly in the requested proportions.
 * @deprecated Use ProportionalStreamCombiner
 */
final class ProportionalStreamMixer extends StreamMixer
{
    /**
     * @var StreamWeight[]
     */
    private $weights;

    /**
     * @param StreamInjector $injector The injector which will perform injection.
     * @param array $stream_weights Array of StreamWeight instances indicating source streams and their relative proportions.
     * @param string $identity The string identifies this stream mixer.
     * @throws TypeMismatchException If some element of the provided array is not a StreamWeight.
     */
    public function __construct(StreamInjector $injector, array $stream_weights, string $identity)
    {
        parent::__construct($injector, $identity);
        $weights = [];
        foreach ($stream_weights as $sw) {
            if ($sw instanceof StreamWeight) {
                $weights[$sw->get_stream()->get_identity()] = $sw;
            } else {
                throw new TypeMismatchException(StreamWeight::class, $sw);
            }
        }
        $this->weights = $weights;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'stream_injector' => $this->injector->to_template(),
            'stream_weight_array' => array_values(array_map(function ($sw) {
                /** @var StreamWeight $sw */
                return $sw->to_template();
            }, $this->weights)),
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $injector_template       = $template['stream_injector'] ?? null;
        $stream_weights_template = $template['stream_weight_array'] ?? null;

        $injector = StreamSerializer::from_template($context->derive($injector_template, 'stream_injector'));
        $stream_weights = [];
        foreach ($stream_weights_template as $i => $sw_template) {
            /** @var StreamWeight $sw */
            $sw = StreamSerializer::from_template($context->derive($sw_template, sprintf('stream_weight_array/%d', $i)));
            $stream_weights[] = $sw;
        }
        return new self($injector, $stream_weights, $context->get_current_identity());
    }

    /**
     * Build a proportional mixture including only the provided streams.
     * @param array $stream_ids The stream ids to include in the mixture.
     * @return ProportionalMixture
     */
    private function build_mixture(array $stream_ids): ProportionalMixture
    {
        $weights = [];
        foreach ($stream_ids as $id) {
            /** @var StreamWeight $sw */
            $sw = $this->weights[$id];
            $weights[] = $sw->get_weight();
        }
        return new ProportionalMixture($stream_ids, $weights);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function mix(
        int $count,
        MultiCursor $cursor,
        ?StreamTracer $tracer,
        InjectionPlan $injection_plan,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $feeds = [];
        foreach ($this->weights as $id => $weight) {
            /** @var StreamWeight $weight */
            $s = $weight->get_stream();
            $res = $s->enumerate($count, $cursor->cursor_for_stream($s), $tracer, $option);
            if ($res->get_size() > 0) {
                $feeds[$id] = array_reverse($res->get_elements());
            }
        }

        $results = [];
        if (!empty($feeds)) {
            $current_mixture = $this->build_mixture(array_keys($feeds));
            for ($i = 0; $i < $count; $i++) {
                $sid = $current_mixture->draw();
                /** @var StreamElement $item */
                $results[] = array_pop($feeds[$sid]);
                if (empty($feeds[$sid])) {
                    unset($feeds[$sid]);
                    if (empty($feeds)) {
                        break;
                    } else {
                        $current_mixture = $this->build_mixture(array_keys($feeds));
                    }
                }
            }
        }

        return new StreamResult(empty($feeds), $results);
    }

    /**
     * @return bool
     */
    #[\Override]
    protected function can_enumerate(): bool
    {
        if (!parent::can_enumerate()) {
            return false;
        }
        foreach ($this->weights as $weight) {
            $stream = $weight->get_stream();
            if ($stream->can_enumerate()) {
                // as long as at least one stream from the mix can be enumerated,
                // the proportional stream mixer will be able to enumerate elements.
                return true;
            }
        }
        return false;
    }
}
