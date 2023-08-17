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

use stdClass;
use Tests\Unit\Tumblr\StreamBuilder\TestUtils;
use Tumblr\StreamBuilder\Helpers;

/**
 * Class HelpersTest
 */
class HelpersTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for test_base64_url_encode test_base64_url_decode
     * @return array
     */
    public function provider_base64_url_encode_decode(): array
    {
        return [
            ['abc', 'YWJj'],
            ['aa?aa>a', 'YWE_YWE-YQ..'],
            ['+/=-_.', 'Ky89LV8u'],
        ];
    }

    /**
     * @dataProvider provider_base64_url_encode_decode
     * @param string $expected The expected output
     * @param string $input The input to decode
     */
    public function test_base64_url_decode(string $expected, string $input)
    {
        $this->assertSame(Helpers::base64UrlDecode($input), $expected);
        $this->assertSame(Helpers::base64UrlEncode(Helpers::base64UrlDecode($input)), $input);
    }

    /**
     * @dataProvider provider_base64_url_encode_decode
     * @param string $input The input to encode
     * @param string $expected The expected output
     */
    public function test_base64_url_encode(string $input, string $expected)
    {
        $this->assertSame(Helpers::base64UrlEncode($input), $expected);
        $this->assertSame(Helpers::base64UrlDecode(Helpers::base64UrlEncode($input)), $input);
    }

    /**
     * Test long_to_bytes
     * @return void
     */
    public function test_long_to_bytes()
    {
        $this->assertSame(8, strlen(Helpers::long_to_bytes('whatever')));
        $this->assertSame("\x00\x00\x00\x00\x00\x00\x00\x00", Helpers::long_to_bytes(0));
        $this->assertSame("\x7F\xFF\xFF\xFF\xFF\xFF\xFF\xFF", Helpers::long_to_bytes(PHP_INT_MAX));
        $this->assertSame("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF", Helpers::long_to_bytes(-1));
        $this->assertSame("\x80\x00\x00\x00\x00\x00\x00\x00", Helpers::long_to_bytes(PHP_INT_MIN));
        $this->assertSame('cooldude', Helpers::long_to_bytes(7165068043502314597));
    }

    /**
     * Test idx2
     * @return void
     */
    public function test_idx2()
    {
        $this->assertSame('v1', Helpers::idx2([ 'k1' => 'v1' ], 'k1', 'k2', null));
        $this->assertSame('v2', Helpers::idx2([ 'k2' => 'v2' ], 'k1', 'k2', null));
        $this->assertSame('v1', Helpers::idx2([ 'k1' => 'v1', 'k2' => 'v2' ], 'k1', 'k2', null));
        $this->assertSame('v2', Helpers::idx2([ 'k1' => 'v1', 'k2' => 'v2' ], 'k2', 'k1', null));
        $this->assertSame('v2', Helpers::idx2([ 'k1' => null, 'k2' => 'v2' ], 'k1', 'k2', null));
        $this->assertNull(Helpers::idx2([ 'k1' => 'v1' ], 'k3', 'k4', null));
    }

    /**
     * Test verify_reordered_elements
     * @return void
     */
    public function test_verify_reordered_elements()
    {
        $o1 = new stdClass();
        $o2 = new stdClass();
        $o3 = new stdClass();

        $this->assertTrue(Helpers::verify_reordered_elements([], []));
        $this->assertTrue(Helpers::verify_reordered_elements([ $o1 ], [ $o1 ]));
        $this->assertTrue(Helpers::verify_reordered_elements([ $o1, $o2 ], [ $o1, $o2 ]));
        $this->assertTrue(Helpers::verify_reordered_elements([ $o1, $o2 ], [ $o2, $o1 ]));
        $this->assertTrue(Helpers::verify_reordered_elements([ $o2, $o1 ], [ $o1, $o2 ]));

        $this->assertFalse(Helpers::verify_reordered_elements([ $o1, $o2 ], [ $o1 ]));
        $this->assertFalse(Helpers::verify_reordered_elements([ $o1, $o2 ], [ $o2 ]));
        $this->assertFalse(Helpers::verify_reordered_elements([ $o1, $o2 ], [ $o3 ]));
        $this->assertFalse(Helpers::verify_reordered_elements([ $o1, $o2 ], [ $o1, $o3 ]));
        $this->assertFalse(Helpers::verify_reordered_elements([ $o1, $o2 ], [ $o2, $o3 ]));
    }

    /**
     * Test for omit_element
     * @return void
     */
    public function test_omit_element()
    {
        $o1 = new stdClass();
        $o2 = new stdClass();
        $o3 = new stdClass();

        $this->assertSame([], Helpers::omit_element([], $o1));
        $this->assertSame([], Helpers::omit_element([ $o1 ], $o1));
        $this->assertSame([], Helpers::omit_element([ $o1, $o1 ], $o1));
        $this->assertSame([ $o1 ], Helpers::omit_element([ $o1 ], $o2));
        $this->assertSame([ $o1 ], Helpers::omit_element([ $o1, $o2, $o2 ], $o2));
        $this->assertSame([ $o2, $o2 ], Helpers::omit_element([ $o1, $o2, $o2 ], $o1));
        $this->assertSame([ $o2, $o3 ], Helpers::omit_element([ $o1, $o2, $o3 ], $o1));
        $this->assertSame([ $o1, $o3 ], Helpers::omit_element([ $o1, $o2, $o3 ], $o2));
        $this->assertSame([ $o1, $o2 ], Helpers::omit_element([ $o1, $o2, $o3 ], $o3));
        $this->assertSame([ $o2, $o2 ], Helpers::omit_element([ $o1, $o1, $o2, $o2 ], $o1));
    }

    /**
     * @dataProvider provider_json_parse
     * @param string $input JSON string
     * @param mixed $expect Expected result
     */
    public function test_json_parse($input, $expect)
    {
        TestUtils::assertSameRecursively($expect, Helpers::json_decode($input));
    }

    /**
     * @see test_json_parse
     * @return array
     */
    public function provider_json_parse()
    {
        return [
            ['[33,42,69]', [33, 42, 69]],
            ['{"foo":1,"bar":2}', ['foo' => 1, 'bar' => 2]],
            ['NULL', null], // check for backwards compatibility w/ php5
            ['null', null],
        ];
    }

    /**
     * @dataProvider provider_json_parse_invalid_syntax
     * @param string $input JSON string
     */
    public function test_json_parse_invalid_syntax($input)
    {
        $this->expectException(\JsonException::class);
        $this->expectExceptionMessage('Syntax error');
        Helpers::json_decode($input);
    }

    /**
     * @see test_json_parse_invalid_syntax
     * @return array
     */
    public function provider_json_parse_invalid_syntax()
    {
        return [
            ['['],
            ['{"foo":'],
            ['{"foo":1,"bar":"blegh"'],
        ];
    }

    /**
     * Test that an error is thrown when Helpers::json_encode() encounters a recursive input
     */
    public function test_json_encode_recursive_input()
    {
        $this->expectException(\JsonException::class);
        $this->expectExceptionMessage('Recursion detected');
        $json_obj = [];
        $json_obj["foo"] = &$json_obj;
        Helpers::json_encode($json_obj);
    }

    /**
     * @dataProvider provider_json_encode
     * @param mixed $input The input value
     * @param string $expect The expected JSON output string
     */
    public function test_json_encode($input, $expect)
    {
        $this->assertSame($expect, Helpers::json_encode($input));
    }

    /**
     * @see test_json_encode
     * @return array
     */
    public function provider_json_encode()
    {
        return [
            [["foo" => 1, "bar" => 2], '{"foo":1,"bar":2}'],
            ['<foo>', '"<foo>"'],
        ];
    }

    /**
     * @dataProvider provider_emoji
     * @param mixed $input The input value
     * @param string $expect The expected JSON output
     */
    public function test_encode_emoji($input, $expect)
    {
        $this->assertSame($expect, Helpers::json_encode($input));
    }

    /**
     * @dataProvider provider_emoji
     * @param string $expect The expected JSON output
     * @param mixed $input The input value
     */
    public function test_parse_emoji($expect, $input)
    {
        TestUtils::assertSameRecursively($expect, Helpers::json_decode($input));
    }

    /**
     * @see test_encode_emoji
     * @see test_parse_emoji
     * @return array
     */
    public function provider_emoji()
    {
        $ret = [
            ['💩', '"💩"'],
            [['💩'], '["💩"]'],
            [['💩' => '💩'], '{"💩":"💩"}'],
            ['💩blegh', '"💩blegh"'],
        ];
        return $ret;
    }

    /**
     * @param mixed $input The input value
     * @param bool $should_null If the input should produce null
     * @dataProvider provideParseNully
     * @return void
     */
    public function testParseNully($input, $should_null)
    {
        $json = Helpers::json_decode((string) $input);

        if ($should_null) {
            $this->assertNull($json);
        } else {
            $this->assertNotNull($json);
        }
    }

    /**
     * @see testParseNully
     * @return iterable
     */
    public function provideParseNully(): iterable
    {
        yield ['', true];
        yield [false, true];
        yield [null, true];
        yield [0, false];
        yield [0.0, false];
        yield ['0', false];
        yield ['0.0', false];
    }
}
