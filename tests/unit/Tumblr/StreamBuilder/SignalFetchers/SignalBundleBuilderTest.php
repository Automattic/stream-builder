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

use Tumblr\StreamBuilder\SignalFetchers\SignalBundleBuilder;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Tests for SignalBundleBuilder
 * @see SignalBundleTest
 */
class SignalBundleBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return void
     */
    public function test_sanity()
    {
        /** @var StreamElement $el1 */
        $el1 = $this->getMockBuilder(LeafStreamElement::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $sbb = new SignalBundleBuilder();
        $sbb->with_signal_for_element($el1, 'foo', 123);

        $sb1 = $sbb->build();
        $this->assertSame(1, count($sb1->get_signals_for_element($el1)));
        $this->assertSame(123, $sb1->get_signal_for_element($el1, 'foo'));

        $sbb->with_signal_for_element($el1, 'foo', 456);

        $sb2 = $sbb->build();
        $this->assertSame(1, count($sb2->get_signals_for_element($el1)));
        $this->assertSame(456, $sb2->get_signal_for_element($el1, 'foo'));

        $sbb->with_signal_for_element($el1, 'bar', 'cool');

        $sb2 = $sbb->build();
        $this->assertSame(2, count($sb2->get_signals_for_element($el1)));
        $this->assertSame(456, $sb2->get_signal_for_element($el1, 'foo'));
        $this->assertSame('cool', $sb2->get_signal_for_element($el1, 'bar'));
    }
}
