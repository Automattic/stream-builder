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

use ReflectionClass;
use Tumblr\StreamBuilder\ConfigTemplateProvider;
use Tumblr\StreamBuilder\Exceptions\TemplateNotFoundException;
use Tumblr\StreamBuilder\Streams\NullStream;
use function sort;

/**
 * Tests for ConfigTemplateProvider
 */
class ConfigTemplateProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Setup context_provider static property.
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $yaml_template_reflector = new \ReflectionClass(\Tumblr\StreamBuilder\YamlTemplateProvider::class);
        $yaml_template_property = $yaml_template_reflector->getProperty('context_provider');
        $yaml_template_property->setAccessible(true);
        $yaml_template_property->setValue([
            'examples' => '../../lib/Tumblr/StreamBuilder/Templates',
        ]);
    }

    /**
     * @return array
     */
    public function get_path_for_context_example_provider()
    {
        return [
            ['foo', CONFIG_DIR . '/config/stream_templates/foo'],
            ['../', CONFIG_DIR . '/config/stream_templates/___'],
            ['foo.bar', CONFIG_DIR . '/config/stream_templates/foo_bar'],
            ['../../passwd', CONFIG_DIR . '/config/stream_templates/______passwd'],
        ];
    }

    /**
     * @dataProvider get_path_for_context_example_provider
     * @param string $input Input to `get_path_for_context`.
     * @param string $output Expected output from `get_path_for_context`.
     * @return void
     */
    public function test_get_path_for_context_example($input, $output)
    {
        $this->assertSame($output, ConfigTemplateProvider::getInstance()->getPathForContext($input));
    }

    /**
     * @return array
     */
    public function get_path_for_context_empty_provider()
    {
        return [
            ['0', \InvalidArgumentException::class],
            ['', \InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider get_path_for_context_empty_provider
     * @param string $input Input to `get_path_for_context`.
     * @param string $error_type Error Type
     */
    public function test_get_path_for_context_empty($input, $error_type)
    {
        $this->expectException($error_type);
        ConfigTemplateProvider::getInstance()->getPathForContext($input);
    }

    /**
     * @return array
     */
    public function get_path_for_template_example_provider()
    {
        return [
            ['dummy', 'foo', CONFIG_DIR . '/config/stream_templates/dummy/foo.json'],
            ['dummy', '.foo', CONFIG_DIR . '/config/stream_templates/dummy/foo.json'],
            ['dummy', '..foo', CONFIG_DIR . '/config/stream_templates/dummy/foo.json'],
            ['dummy', '.....foo', CONFIG_DIR . '/config/stream_templates/dummy/foo.json'],
            ['dummy', 'foo.bar', CONFIG_DIR . '/config/stream_templates/dummy/foo.bar.json'],
            ['dummy', '.foo.bar', CONFIG_DIR . '/config/stream_templates/dummy/foo.bar.json'],
            ['dummy', 'foo.bar.baz', CONFIG_DIR . '/config/stream_templates/dummy/foo.bar.baz.json'],
            ['dummy', '.foo.bar.baz', CONFIG_DIR . '/config/stream_templates/dummy/foo.bar.baz.json'],
            ['dummy', 'foo.bar.baz.quux', CONFIG_DIR . '/config/stream_templates/dummy/foo.bar.baz.quux.json'],
            ['dummy', 'foo.bar/baz/quux', CONFIG_DIR . '/config/stream_templates/dummy/foo.bar_baz_quux.json'],
            ['dummy', 'foo/bar.baz/quux', CONFIG_DIR . '/config/stream_templates/dummy/foo_bar.baz_quux.json'],
            ['dummy', 'foo/bar/baz.quux', CONFIG_DIR . '/config/stream_templates/dummy/foo_bar_baz.quux.json'],
            ['dummy', 'foo/bar/baz/quux', CONFIG_DIR . '/config/stream_templates/dummy/foo_bar_baz_quux.json'],
        ];
    }

    /**
     * @dataProvider get_path_for_template_example_provider
     * @param string $input_context Input context to `get_path_for_template`.
     * @param string $input_name Input template name to `get_path_for_template`.
     * @param string $output Expected output from `get_path_for_template`.
     * @return void
     */
    public function test_get_path_for_template_example($input_context, $input_name, $output)
    {
        $this->assertSame($output, ConfigTemplateProvider::getInstance()->getPathForTemplate($input_context, $input_name));
    }

    /**
     * @return array
     */
    public function get_path_for_template_empty_provider()
    {
        return [
            ['dummy', '0', \InvalidArgumentException::class],
            ['dummy', '', \InvalidArgumentException::class],
            ['dummy', '...', \InvalidArgumentException::class],
            ['0', 'legit.af', \InvalidArgumentException::class],
            ['', 'legit.af', \InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider get_path_for_template_empty_provider
     * @param string $input_context Input context to `getPathForTemplate`.
     * @param string $input_name Input template name to `getPathForTemplate`.
     * @param string $error_type Error Type.
     */
    public function test_get_path_for_template_empty($input_context, $input_name, $error_type)
    {
        $this->expectException($error_type);
        ConfigTemplateProvider::getInstance()->getPathForTemplate($input_context, $input_name);
    }

    /**
     * @return void
     */
    public function testGetTemplateThrowsExceptionOnInvalidTemplate(): void
    {
        $this->expectException(TemplateNotFoundException::class);
        ConfigTemplateProvider::getInstance()->get_template('foo7', 'bar7');
    }

    /**
     * @return void
     */
    public function testGetTemplateWithValidContextAndName(): void
    {
        $template = ConfigTemplateProvider::getInstance()->get_template('examples', 'empty');
        $this->assertSame(NullStream::class, $template['_type']);
    }

    /**
     * @return void
     */
    public function testListContexts(): void
    {
        $contexts = ConfigTemplateProvider::getInstance()->list_contexts();

        $expectation = [
            'examples',
        ];
        sort($contexts);
        $this->assertSame($expectation, $contexts);
    }

    /**
     * Provide for testResolveSubtemplate
     * @return array
     */
    public function provideSubtemplateSubstitute(): array
    {
        return [
            [[], []],
            [
                ['_type' => 'test/components.test', '_component' => 'test'],
                ['_type' => 'XXXX', 'inner' => ['_type' => 'test/components.test', '_component' => 'test']],
            ],
            [
                [
                    '_type' => 'test/components.test',
                    '_component' => 'test',
                ],
                [
                    '_type' => 'XXXX',
                    'inner' => [
                        '_type' => 'YYYY',
                        'injector' => [
                            '_type' => 'test/components.test',
                            '_component' => 'test',
                        ],
                    ],
                ],
            ],
            [
                [],
                ['_type' => 'XXXX', 'inner' => ['_type' => 'YYYY', 'injector' => ['_type' => 'test_stream']]],
            ],
        ];
    }

    /**
     * Test parse test template with subtemplate.
     * @dataProvider provideSubtemplateSubstitute
     * @param array $expected Expected resolved template array.
     * @param array $input Input template array.
     * @return void
     */
    public function testResolveComponent(array $expected, array $input): void
    {
        $obj = ConfigTemplateProvider::getInstance();
        $ref = new \ReflectionClass($obj);
        $reflected_method = $ref->getMethod('resolveComponent');
        $reflected_method->setAccessible(true);
        $actual = $reflected_method->invoke($obj, $input, 'test');
        $this->assertSame($expected, $actual);
    }
}
