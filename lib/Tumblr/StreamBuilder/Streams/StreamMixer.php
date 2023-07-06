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
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function is_null;
use function max;

/**
 * Abstract Multi-Cursor mixer, combining injections with a mixing strategy (and attempting to do the heavy lifting of making sense of cursors).
 * @deprecated Please stop implementing this StreamMixer logic, please use an InjectedStream for any injector + stream logic.
 * A StreamMixer will be separated from injector logic and becomes a purely MultiCursorStream.
 */
abstract class StreamMixer extends Stream
{
    /**
     * NOTE: Force a mixer to have an injector is weird. We are slowly deprecate this logic.
     * Instead, use InjectedStream as the wrapper of injector + stream logic.
     * @var StreamInjector
     */
    protected $injector;

    /**
     * StreamMixer Constructor
     * @param StreamInjector $injector The injector used during enumeration.
     * @param string $identity The string identifies the mixer.
     */
    public function __construct(StreamInjector $injector, string $identity)
    {
        $this->injector = $injector;
        parent::__construct($identity);
    }

    /**
     * @inheritDoc
     */
    final protected function _enumerate(
        int $count,
        StreamCursor $cursor = null,
        StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        if (is_null($cursor)) {
            $cursor = new MultiCursor([]);
        } elseif (!($cursor instanceof MultiCursor)) {
            throw new InappropriateCursorException($this, $cursor);
        }
        /** @var MultiCursor $cursor */
        $inj_plan = $this->injector->plan_injection($count, $this, $cursor->get_injector_state(), $tracer);
        $new_cursor = $cursor->with_injector_state($inj_plan->get_injector_state());

        $count = max(1, $count - $inj_plan->get_injection_count());
        $mix_result = $this->mix($count, $cursor, $tracer, $inj_plan, $option);

        $derived_results = [];
        foreach ($mix_result->get_elements() as $i) {
            /** @var StreamElement $i */
            $derived_results[] = new DerivedStreamElement($i, $this->get_identity(), $new_cursor->combine_from($i));
        }
        return $inj_plan->apply(new StreamResult($mix_result->is_exhaustive(), $derived_results));
    }

    /**
     * Mix stream elements from it's inner streams, distribute MultiCursor on each element.
     * @param int $count How many slots need to be filled.
     * @param MultiCursor $cursor The cursor for mixing
     * @param StreamTracer|null $tracer The tracer traces mix process.
     * @param InjectionPlan $injection_plan The injection plan used as reference.
     * @param EnumerationOptions|null $option The option to enumerate & mix
     * @return StreamResult
     */
    abstract protected function mix(
        int $count,
        MultiCursor $cursor,
        ?StreamTracer $tracer,
        InjectionPlan $injection_plan,
        ?EnumerationOptions $option = null
    ): StreamResult;
}
