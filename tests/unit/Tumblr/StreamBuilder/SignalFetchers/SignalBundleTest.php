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

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\SignalFetchers\SignalBundle;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;

/**
 * Test for SignalBundle.
 * Note that a lot of code coverage is provided by the SignalBundleBuilderTest,
 * so we're going to fill in some blanks here.
 * @see SignalBundleBuilderTest
 */
class SignalBundleTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Various tests for combine_all ensuring proper precedence
     * @return void
     */
    public function test_combine()
    {
        /** @var StreamElement $e1 */
        $e1 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $e2 */
        $e2 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $e3 */
        $e3 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $e4 */
        $e4 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $e5 */
        $e5 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $sb1 = new SignalBundle($sig1 = [
            Helpers::memory_element_id($e1) => [ 's1' => 123, 's2' => 'abc' ],
            Helpers::memory_element_id($e2) => [ 's1' => 456, 's2' => 'def' ],
        ]);

        $sb2 = new SignalBundle($sig2 = [
            Helpers::memory_element_id($e2) => [ 's2' => 'ghi', 's3' => 100.22 ],
            Helpers::memory_element_id($e3) => [ 's2' => 'jkl', 's3' => 200.44 ],
        ]);

        $sb3 = new SignalBundle($sig3 = [
            Helpers::memory_element_id($e3) => [ 's3' => 400.88, 's4' => [ 1, 2, 3, 4 ] ],
            Helpers::memory_element_id($e4) => [ 's4' => [ 3, 4, 5, 6 ] ],
        ]);

        $sb4 = new SignalBundle($sig4 = [
            Helpers::memory_element_id($e4) => [ 's4' => [ 30, 40 ] ],
            Helpers::memory_element_id($e5) => [ 's4' => [ 50, 60 ] ],
        ]);

        $this->assertEquals(new SignalBundle([]), SignalBundle::combine_all([]));
        $this->assertEquals($sb1, SignalBundle::combine_all([ $sb1 ]));
        $this->assertEquals($sb1, SignalBundle::combine_all([ $sb1, $sb1 ]));
        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e1) => ['s1' => 123, 's2' => 'abc'],
            Helpers::memory_element_id($e2) => ['s1' => 456, 's2' => 'ghi', 's3' => 100.22],
            Helpers::memory_element_id($e3) => ['s2' => 'jkl', 's3' => 200.44],
        ]), SignalBundle::combine_all([ $sb1, $sb2 ]));
        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e1) => ['s1' => 123, 's2' => 'abc'],
            Helpers::memory_element_id($e2) => ['s1' => 456, 's2' => 'def', 's3' => 100.22],
            Helpers::memory_element_id($e3) => ['s2' => 'jkl', 's3' => 200.44],
        ]), SignalBundle::combine_all([ $sb2, $sb1 ]));
        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e2) => [ 's2' => 'ghi', 's3' => 100.22 ],
            Helpers::memory_element_id($e3) => [ 's2' => 'jkl', 's3' => 400.88, 's4' => [ 1, 2, 3, 4 ] ],
            Helpers::memory_element_id($e4) => [ 's4' => [ 3, 4, 5, 6 ] ],
        ]), SignalBundle::combine_all([ $sb2, $sb3 ]));

        $this->assertEquals(new SignalBundle(array_merge($sig1, $sig3)), SignalBundle::combine_all([ $sb1, $sb3 ]));
        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e3) => [ 's3' => 400.88, 's4' => [ 1, 2, 3, 4 ] ],
            Helpers::memory_element_id($e4) => [ 's4' => [ 30, 40 ] ],
            Helpers::memory_element_id($e5) => [ 's4' => [ 50, 60 ] ],
        ]), SignalBundle::combine_all([ $sb3, $sb4 ]));

        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e1) => [ 's1' => 123, 's2' => 'abc' ],
            Helpers::memory_element_id($e2) => [ 's1' => 456, 's2' => 'ghi', 's3' => 100.22 ],
            Helpers::memory_element_id($e3) => [ 's2' => 'jkl', 's3' => 400.88, 's4' => [ 1, 2, 3, 4 ] ],
            Helpers::memory_element_id($e4) => [ 's4' => [ 3, 4, 5, 6 ] ],
        ]), SignalBundle::combine_all([ $sb1, $sb2, $sb3 ]));
        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e1) => [ 's1' => 123, 's2' => 'abc' ],
            Helpers::memory_element_id($e2) => [ 's1' => 456, 's2' => 'def', 's3' => 100.22 ],
            Helpers::memory_element_id($e3) => [ 's2' => 'jkl', 's3' => 200.44, 's4' => [ 1, 2, 3, 4 ] ],
            Helpers::memory_element_id($e4) => [ 's4' => [ 3, 4, 5, 6 ] ],
        ]), SignalBundle::combine_all([ $sb3, $sb2, $sb1 ]));

        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e1) => [ 's1' => 123, 's2' => 'abc' ],
            Helpers::memory_element_id($e2) => [ 's1' => 456, 's2' => 'ghi', 's3' => 100.22 ],
            Helpers::memory_element_id($e3) => [ 's2' => 'jkl', 's3' => 400.88, 's4' => [ 1, 2, 3, 4 ] ],
            Helpers::memory_element_id($e4) => [ 's4' => [ 30, 40 ] ],
            Helpers::memory_element_id($e5) => [ 's4' => [ 50, 60 ] ],
        ]), SignalBundle::combine_all([ $sb1, $sb2, $sb3, $sb4 ]));
        $this->assertEquals(new SignalBundle([
            Helpers::memory_element_id($e1) => [ 's1' => 123, 's2' => 'abc' ],
            Helpers::memory_element_id($e2) => [ 's1' => 456, 's2' => 'def', 's3' => 100.22 ],
            Helpers::memory_element_id($e3) => [ 's2' => 'jkl', 's3' => 200.44, 's4' => [ 1, 2, 3, 4 ] ],
            Helpers::memory_element_id($e4) => [ 's4' => [ 3, 4, 5, 6 ] ],
            Helpers::memory_element_id($e5) => [ 's4' => [ 50, 60 ] ],
        ]), SignalBundle::combine_all([ $sb4, $sb3, $sb2, $sb1 ]));
    }

    /**
     * Test that the combine method fails when given a non-SignalBundle
     * @return void
     */
    public function test_combine_invalid()
    {
        $this->expectException(\Tumblr\StreamBuilder\Exceptions\TypeMismatchException::class);
        /** @var StreamElement $e1 */
        $e1 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $e2 */
        $e2 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $sb1 = new SignalBundle($sig1 = [
            Helpers::memory_element_id($e1) => [ 's1' => 123, 's2' => 'abc' ],
            Helpers::memory_element_id($e2) => [ 's1' => 456, 's2' => 'def' ],
        ]);

        $bad = new \stdClass();
        SignalBundle::combine_all([ $sb1, $bad ]);
    }
}
