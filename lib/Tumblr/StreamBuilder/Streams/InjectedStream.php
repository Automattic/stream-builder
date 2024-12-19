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
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\InjectedStreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * Stream which performs an injection of one stream into some other stream.
 */
final class InjectedStream extends WrapStream
{
    /**
     * @var StreamInjector
     */
    private $injector;

    /**
     * InjectedStream constructor.
     *
     * @param Stream $inner             The stream which will be injected into.
     * @param StreamInjector $injector  The injector used for injection.
     * @param string $identity          The identity of this stream.
     */
    public function __construct(Stream $inner, StreamInjector $injector, string $identity)
    {
        parent::__construct($inner, $identity);
        $this->injector = $injector;
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
        if (is_null($cursor)) {
            $cursor = new InjectedStreamCursor(null, []);
        } elseif (!($cursor instanceof InjectedStreamCursor)) {
            throw new InappropriateCursorException($this, $cursor);
        }

        try {
            /** @var InjectedStreamCursor $cursor */
            $inj_plan = $this->injector->plan_injection($count, $this, $cursor->get_injector_state(), $tracer);
        } catch (\Throwable $t) {
            // failed injection plan enumeration should not affect main stream's enumeration
            $log = StreamBuilder::getDependencyBag()->getLog();
            $log->exception($t, $this->get_identity());
            $inj_plan = new InjectionPlan([]);
        }
        $new_inj_state = $inj_plan->get_injector_state();

        $inner_count = max(1, $count - $inj_plan->get_injection_count());
        $inner_result = $this->getInner()->enumerate($inner_count, $cursor->get_inner_cursor(), $tracer, $option);

        $derived_results = [];
        foreach ($inner_result->get_elements() as $i) {
            $derived_results[] = new DerivedStreamElement($i, $this->get_identity(), new InjectedStreamCursor($i->get_cursor(), $new_inj_state));
        }

        $result = new StreamResult($inner_result->is_exhaustive(), $derived_results);
        return $inj_plan->apply($result);
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'injector' => $this->injector->to_template(),
            'stream' => $this->getInner()->to_template(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $injector   = $context->deserialize_required_property('injector');
        $stream     = $context->deserialize_required_property('stream');

        return new self($stream, $injector, $context->get_current_identity());
    }

    /**
     * @inheritDoc
     */
    public function estimate_count(): ?int
    {
        // TODO: update with injection
        return $this->getInner()->estimate_count();
    }
}
