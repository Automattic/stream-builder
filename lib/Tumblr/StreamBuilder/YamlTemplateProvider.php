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

use Symfony\Component\Yaml\Yaml;

/**
 * Stream template stored as YAML.
 */
class YamlTemplateProvider extends TemplateProvider
{
    /** @var YamlTemplateProvider */
    private static $instance;

    /**
     * @var string[] In alphabetical order
     * [context_name => TemplateProvider]
     */
    private static $context_provider;

    /**
     * YamlTemplateProvider constructor.
     */
    public function __construct()
    {
        static::$context_provider = StreamBuilder::getDependencyBag()->getContextProvider();
    }

    /**
     * @return self
     */
    public static function getInstance(): self
    {
        if (!static::$instance instanceof self) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function getTemplate(string $context, string $name, ?string $component = null): ?array
    {
        try {
            $path = $this->getPathForTemplate($context, $name);
            /** @psalm-suppress ReservedWord */
            $template = Yaml::parseFile($path);
        } catch (\Exception $e) {
            return null;
        }
        if (!empty($component)) {
            $template = $this->resolveComponent($template, $component);
            if (empty($template)) {
                return null;
            }
        }
        /** @var array $template */
        return $template;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function listContexts(): array
    {
        return array_keys(static::$context_provider);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function getPathForTemplate(string $context, string $template_name): string
    {
        // leading periods are removed and forward slashes are replaced with underscores, to prevent upwards traversal.
        $template_name = ltrim(str_replace('/', '_', $template_name), '.');
        if (empty($template_name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        return sprintf(
            '%s/%s.yml',
            $this->getPathForContext($context),
            $template_name
        );
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getPathForContext(string $context): string
    {
        if (empty($context)) {
            throw new \InvalidArgumentException('Context cannot be empty.');
        }
        // forward slashes and periods are replaced with underscores, to prevent upwards traversal.
        $context = preg_replace('/[\/.]/', '_', $context);
        if (empty(static::$context_provider[$context])) {
            throw new \InvalidArgumentException("Context $context not defined for " . get_class($this));
        }
        return sprintf(
            '%s/%s',
            StreamBuilder::getDependencyBag()->getBaseDir(),
            static::$context_provider[$context]
        );
    }
}
