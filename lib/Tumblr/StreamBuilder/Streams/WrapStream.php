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

/**
 * A stream that wraps an inner stream.
 */
abstract class WrapStream extends Stream
{
    /**
     * @var Stream Inner contained stream.
     */
    private $inner;

    /**
     * @param Stream $inner The inner contained stream.
     * @param string $identity The string identifies the stream.
     */
    public function __construct(Stream $inner, string $identity)
    {
        parent::__construct($identity);
        $this->inner = $inner;
    }

    /**
     * @return Stream
     */
    public function getInner(): Stream
    {
        return $this->inner;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['inner'] = $this->getInner()->to_template();
        return $base;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function can_enumerate(): bool
    {
        return parent::can_enumerate() && $this->inner->can_enumerate();
    }
}
