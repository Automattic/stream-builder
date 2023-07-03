<?php declare(strict_types=1);

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

namespace Test\Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Class StreamElementTest
 */
class StreamElementTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test get_provider
     */
    public function test_get_provider_identity()
    {
        /** @var StreamElement $el */
        $el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool'])->getMockForAbstractClass();

        $this->assertSame('cool', $el->get_provider_identity());
    }

    /**
     * Test get_cursor
     */
    public function test_get_cursor()
    {
        /** @var StreamElement $el */
        $el = $this->getMockBuilder(StreamElement::class)->setConstructorArgs(['cool'])->getMockForAbstractClass();

        $this->assertNull($el->get_cursor());
    }
}
