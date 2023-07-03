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

use Tumblr\StreamBuilder\Streams\NullStream;
use Tumblr\StreamBuilder\YamlTemplateProvider;

/**
 * Test for YamlTemplateProvider
 */
class YamlTemplateProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test a simple template get.
     * @return void
     */
    public function testGetTemplateWithValidContextAndName(): void
    {
        $provider = YamlTemplateProvider::getInstance();
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('getTemplate');
        $method->setAccessible(true);
        $template = $method->invoke($provider, 'examples', 'empty');
        $this->assertSame(NullStream::class, $template['_type']);
    }

    /**
     * Test list templates
     * @return void
     */
    public function testListTemplates(): void
    {
        $provider = YamlTemplateProvider::getInstance();
        $templates = $provider::list_templates('examples');
        $this->assertTrue(count($templates) >= 1);
    }

    /**
     * Test list contexts.
     * @return void
     */
    public function testListContexts(): void
    {
        $provider = YamlTemplateProvider::getInstance();
        $contexts = $provider::list_contexts();
        $this->assertTrue(in_array('examples', $contexts, true));
    }

    /**
     * Test component parsing.
     * @return void
     */
    public function testComponentParsing(): void
    {
        $provider = YamlTemplateProvider::getInstance();
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('getTemplate');
        $method->setAccessible(true);
        $template = $method->invoke($provider, 'examples', 'empty', 'examples');
        $this->assertSame(NullStream::class, $template['_type']);
    }
}
