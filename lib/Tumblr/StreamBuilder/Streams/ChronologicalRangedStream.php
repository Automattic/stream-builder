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
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamResult;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;

/**
 * A Stream which enumerates with chronological enumeration options
 */
final class ChronologicalRangedStream extends WrapStream
{
    /**
     * @var int|null The min age for the chronological element
     */
    private ?int $min_age_in_seconds;

    /**
     * @var int|null The max age for the chronological element
     */
    private ?int $max_age_in_seconds;

    /**
     * ChronologicalRangedStream constructor.
     * @param Stream $inner The inner stream to apply the chronological range
     * @param string $identity Identity of the stream
     * @param int|null $min_age The min age if any
     * @param int|null $max_age The max age if any
     * @throws \InvalidArgumentException In inner stream does not support chronological range
     */
    public function __construct(Stream $inner, string $identity, ?int $min_age = null, ?int $max_age = null)
    {
        parent::__construct($inner, $identity);
        if (!$this->getInner()->can_enumerate_with_time_range()) {
            throw new \InvalidArgumentException('Inner stream must support chronological enumeration');
        }
        $this->min_age_in_seconds = $min_age;
        $this->max_age_in_seconds  = $max_age;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    final protected function _enumerate(
        int $count,
        ?StreamCursor $cursor = null,
        ?StreamTracer $tracer = null,
        ?EnumerationOptions $option = null
    ): StreamResult {
        $now = time();
        $max_mills = is_null($this->min_age_in_seconds) ? null : ($now - $this->min_age_in_seconds) * 1000;
        $min_mills = is_null($this->max_age_in_seconds) ? null : ($now - $this->max_age_in_seconds) * 1000;
        $option = new EnumerationOptions($max_mills, $min_mills);
        return $this->getInner()->enumerate($count, $cursor, $tracer, $option);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        $base = [
            '_type' => get_class($this),
            'inner' => $this->getInner()->to_template(),
        ];
        if ($this->min_age_in_seconds) {
            $base['min_age'] = $this->min_age_in_seconds;
        }
        if ($this->max_age_in_seconds) {
            $base['max_age'] = $this->max_age_in_seconds;
        }
        return $base;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        $min_age = $context->get_meta_by_key('min_age') ?? $context->get_optional_property('min_age');
        $max_age = $context->get_meta_by_key('max_age') ?? $context->get_optional_property('max_age');
        return new self(
            $context->deserialize_required_property('inner'),
            $context->get_current_identity(),
            $min_age,
            $max_age
        );
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function can_enumerate_with_time_range(): bool
    {
        return true;
    }
}
