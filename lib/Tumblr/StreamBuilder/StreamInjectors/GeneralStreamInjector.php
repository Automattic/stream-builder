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

namespace Tumblr\StreamBuilder\StreamInjectors;

use Tumblr\StreamBuilder\InjectionAllocators\InjectionAllocator;
use Tumblr\StreamBuilder\InjectionPlan;
use Tumblr\StreamBuilder\Interfaces\User;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursorSerializer;
use Tumblr\StreamBuilder\StreamElementInjection;
use Tumblr\StreamBuilder\Streams\Stream;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use function count;
use function array_shift;
use function array_map;

/**
 * A general injector which enumerates elements from the inner stream and injects to a main stream with
 * slots calculated by allocator.
 * *Caution* cursor is by default null for each page's injection content enumeration.
 */
class GeneralStreamInjector extends StreamInjector
{
    /**
     * @var Stream Inner stream which provides the injection content.
     */
    private Stream $injection_content_stream;

    /**
     * @var InjectionAllocator Allocator which controls which spots to inject.
     */
    private InjectionAllocator $allocator;

    /** @var User|null User */
    private ?User $user;

    /**
     * GeneralStreamInjector constructor.
     * @param Stream $injection_content_stream Inner stream which provides the injection content.
     * @param InjectionAllocator $allocator  Allocator which controls which spots to inject.
     * @param string $identity See Identifiable.
     * @param User|null $user User
     */
    public function __construct(
        Stream $injection_content_stream,
        InjectionAllocator $allocator,
        string $identity,
        ?User $user = null
    ) {
        $this->allocator = $allocator;
        $this->injection_content_stream = $injection_content_stream;
        $this->user = $user;
        parent::__construct($identity);
    }

    /**
     * @inheritDoc
     */
    protected function _plan_injection(
        int $page_size,
        Stream $requesting_stream,
        array $state = null,
        StreamTracer $tracer = null
    ): InjectionPlan {
        $allocate_result = $this->allocator->allocate($page_size, $state);
        $cursor = $this->cursorFromInjectionState($state ?? []);

        $slots = $allocate_result->get_allocate_output();
        if (empty($slots)) {
            // No injection slots in this page
            return new InjectionPlan([], $allocate_result->get_injector_state());
        }
        $plan = [];
        // cursor is not supported for now
        $injection_contents = $this->injection_content_stream->enumerate(
            count($slots),
            $cursor,
            $tracer
        );

        if ($injection_contents->get_size() === 0) {
            // No available content to inject
            return new InjectionPlan([], $allocate_result->get_injector_state());
        }
        $injection_elements = $injection_contents->get_elements();
        foreach ($slots as $slot) {
            $element = array_shift($injection_elements);
            if (!$element) {
                break;
            }
            // add it to the plan
            $plan[$slot] = new StreamElementInjection($this, $element);
        }

        $new_state = $this->cursorToInjectionState(
            $injection_contents->get_combined_cursor(),
            $allocate_result->get_injector_state() ?? []
        );
        $plan = array_map(function (StreamElementInjection $p) {
            $p->get_element()->set_cursor(null);
            return $p;
        }, $plan);
        return new InjectionPlan($plan, $new_state);
    }

    /**
     * Instantiate cursor from injection state.
     * @param array $state Injection state.
     * @return StreamCursor|null
     */
    protected function cursorFromInjectionState(array $state): ?StreamCursor
    {
        return StreamCursorSerializer::decodeCursor(
            $state['cursor'][$this->injection_content_stream->get_identity(true)] ?? null,
            $this->user instanceof User ? $this->user->getUserId() : User::ANONYMIZED_USER_ID
        );
    }

    /**
     * Put cursor info into injection state array.
     * @param StreamCursor|null $cursor Stream cursor
     * @param array $state Injection state array.
     * @return array
     */
    protected function cursorToInjectionState(?StreamCursor $cursor, array $state): array
    {
        $state['cursor'][$this->injection_content_stream->get_identity(true)] = StreamCursorSerializer::encodeCursor(
            $cursor,
            $this->user instanceof User ? $this->user->getUserId() : User::ANONYMIZED_USER_ID,
            'injection'
        );
        return $state;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['inner'] = $this->injection_content_stream->to_template();
        $base['allocator'] = $this->allocator->to_template();
        return $base;
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
        $user = $context->get_meta_by_key('user');
        return new self(
            $context->deserialize_required_property('inner'),
            $context->deserialize_required_property('allocator'),
            $context->get_current_identity(),
            $user
        );
    }
}
