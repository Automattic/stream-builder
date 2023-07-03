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

namespace Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamInjectors\StreamInjector;

/**
 * A StreamInjection that has a pre-computed result. Unless you need to defer computation of your injection, you should use this.
 */
class StreamElementInjection extends StreamInjection
{
    /** @var StreamElement */
    protected $element;

    /**
     * @param StreamInjector $injector The injector which performed this injection.
     * @param StreamElement $element The element to be injected.
     */
    public function __construct(StreamInjector $injector, StreamElement $element)
    {
        parent::__construct($injector);
        $this->element = $element;
        if (empty($element->getComponent())) {
            $this->element->setComponent($injector->getComponent());
        }
    }

    /**
     * @inheritDoc
     */
    public function execute(int $position, array $elements)
    {
        return $this->element;
    }

    /**
     * To get the inner stream element.
     * @return StreamElement
     */
    public function get_element(): StreamElement
    {
        return $this->element;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return sprintf('Injection(%s)', $this->element);
    }
}
