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

use Tumblr\StreamBuilder\ConfigTemplateProvider;
use Tumblr\StreamBuilder\TemplateProvider;

/**
 * Tests for TemplateProvider
 */
class TemplateProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test parse test template name method.
     * @return void
     */
    public function testParseTestTemplateName(): void
    {
        $template_name = TemplateProvider::parseTestTemplateName('default', 'not_exisiting_template', 'default');
        $this->assertSame($template_name, 'default');
    }

    /**
     * @return void
     */
    public function testListTemplates(): void
    {
        $templates = TemplateProvider::list_templates('examples');
        // Not sure what to assert here but let's assume more than 3
        $this->assertGreaterThan(0, count($templates));
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

    /**
     * Data provider for testTemplatesExists()
     * @return array
     */
    public function getTemplates(): array
    {
        return array_map(function ($name) {
            return [$name];
        }, TemplateProvider::list_templates('dashboard'));
    }

    /**
     * Test template_exists method works for all templates.
     * @dataProvider getTemplates
     * @param string $template Template name.
     * @return void
     */
    public function testTemplateExist(string $template): void
    {
        $exists = TemplateProvider::template_exists('dashboard', $template);
        if (!$exists) {
            echo $template;
        }
        $this->assertTrue(TemplateProvider::template_exists('dashboard', $template));
    }
}
