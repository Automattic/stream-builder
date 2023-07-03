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

/**
 * Template provider that reads templates in a separate config space.
 */
class ConfigTemplateProvider extends TemplateProvider
{
    /**
     * @var array[]
     */
    private static $cache = [];

    /** @var ConfigTemplateProvider */
    private static $instance;

    /**
     * Constructor.
     */
    private function __construct()
    {
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
    protected function getTemplate(string $context, string $name, ?string $component = null): ?array
    {
        $path = $this->getPathForTemplate($context, $name);
        $template = static::$cache[$path] ?? null;
        if (is_null($template)) {
            if (is_readable($path) && ($content = file_get_contents($path))) {
                static::$cache[$path] = $template = Helpers::json_decode($content);
            } else {
                return null;
            }
        }
        if (!empty($component)) {
            $template = $this->resolveComponent($template, $component);
            if (empty($template)) {
                return null;
            }
        }
        return $template;
    }

    /**
     * @inheritDoc
     */
    public function getPathForContext(string $context): string
    {
        if (empty($context)) {
            throw new \InvalidArgumentException('Context cannot be empty.');
        }
        $config_dir = StreamBuilder::getDependencyBag()->getConfigDir() ?? 'config';
        // forward slashes and periods are replaced with underscores, to prevent upwards traversal.
        $context = preg_replace('/[\/.]/', '_', $context);
        return sprintf('%s/config/stream_templates/%s', $config_dir, $context);
    }

    /**
     * @inheritDoc
     */
    public function getPathForTemplate(string $context, string $template_name): string
    {
        // leading periods are removed and forward slashes are replaced with underscores, to prevent upwards traversal.
        $template_name = ltrim(str_replace('/', '_', $template_name), '.');
        if (empty($template_name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        $name_split = explode('.', $template_name, 2);
        if (count($name_split) === 2) {
            return sprintf('%s/%s.%s.json', $this->getPathForContext($context), $name_split[0], $name_split[1]);
        }
        return sprintf('%s/%s.json', $this->getPathForContext($context), $template_name);
    }

    /**
     * @inheritDoc
     */
    public function listContexts(): array
    {
        $names = [];

        $config_dir = StreamBuilder::getDependencyBag()->getConfigDir() ?? 'config';
        $abs_path = sprintf('%s/config/stream_templates', $config_dir);
        foreach (scandir($abs_path) as $rel_subpath) {
            if (empty($rel_subpath) || $rel_subpath[0] === '.') {
                continue;
            }
            $abs_subpath = sprintf('%s/%s', $abs_path, $rel_subpath);
            if (!is_dir($abs_subpath)) {
                continue;
            }
            $names[] = $rel_subpath;
        }
        return $names;
    }
}
