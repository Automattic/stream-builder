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
use Tumblr\StreamBuilder\ProportionalMixture;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\StreamWeight;

/**
 * A StreamMixer which draws elements randomly in the requested proportions.
 */
final class ProportionalStreamCombiner extends StreamCombiner
{
    /**
     * @var StreamWeight[]
     */
    private $weights;

    /**
     * @param array $stream_weights Array of StreamWeight instances indicating source streams and their relative proportions.
     * @param string $identity The string identifies this stream mixer.
     * @throws TypeMismatchException If some element of the provided array is not a StreamWeight.
     */
    public function __construct(array $stream_weights, string $identity)
    {
        parent::__construct($identity);
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
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'stream_weight_array' => array_values(array_map(function ($sw) {
                /** @var StreamWeight $sw */
                return $sw->to_template();
            }, $this->weights)),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $template = $context->get_template();
        $stream_weights_template = $template['stream_weight_array'] ?? null;

        $stream_weights = [];
        foreach ($stream_weights_template as $i => $sw_template) {
            /** @var StreamWeight $sw */
            $sw = StreamSerializer::from_template($context->derive($sw_template, sprintf('stream_weight_array/%d', $i)));
            $stream_weights[] = $sw;
        }
        return new self($stream_weights, $context->get_current_identity());
    }

    /**
     * Build a proportional mixture including only the non-exhausted streams.
     * @param array[] $feeds The feeds that might be included in the mixture.
     * @return ProportionalMixture|null
     */
    private function build_mixture(array $feeds): ?ProportionalMixture
    {
        $stream_ids = [];
        foreach ($feeds as $key => $info) {
            if ((!empty($info['results'])) || (!$info['exhaust'])) {
                // only include non-exhausted OR non-empty streams in mixture.
                $stream_ids[] = $key;
            }
        }
        if (empty($stream_ids)) {
            return null;
        }
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
    protected function combine(
        int $count,
        MultiCursor $cursor,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        // current map of feed ids we can draw from
        $feeds = [];
        foreach ($this->weights as $id => $weight) {
            /** @var StreamWeight $weight */
            $s = $weight->get_stream();
            $feeds[$id] = [
                'stream' => $s,
                'results' => [],
                'exhaust' => false,
            ];
        }
        $results = [];
        $current_mixture = $this->build_mixture($feeds);
        while ((!is_null($current_mixture)) && count($results) < $count) {
            $sid = $current_mixture->draw(); // draw a random stream
            if (empty($feeds[$sid]['results']) && !$feeds[$sid]['exhaust']) {
                // refill enumerations for this non-exhausted stream:
                // it will either become non-empty, or it will become exhausted. if it becomes non-empty it might
                // still become exhausted, if the source stream says it is.
                /** @var Stream $current_stream */
                $current_stream = $feeds[$sid]['stream'];
                $res = $current_stream->enumerate($count, $cursor->cursor_for_stream($current_stream), $tracer, $option);
                $feeds[$sid]['results'] = array_reverse($res_elems = $res->get_elements());
                if ($res->is_exhaustive() || count($res_elems) == 0) {
                    $feeds[$sid]['exhaust'] = true;
                }
            }

            // now $feeds[$sid] is one of:
            //  - empty and exhausted - in which case we rebuild the mixture so the next loop wont try this feed again
            //  - non-empty (maybe exhausted) - in which case we take an element
            if (empty($feeds[$sid]['results'])) {
                $current_mixture = $this->build_mixture($feeds);
            } else {
                $results[] = array_pop($feeds[$sid]['results']);
            }
        }

        // we are exhausted if the mixture became null
        return new StreamResult(is_null($current_mixture), $results);
    }
}
