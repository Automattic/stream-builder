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

use Tumblr\StreamBuilder\Exceptions\TemplateNotFoundException;

/**
 * Provide stream templates.
 * Templates are identified by a context and name.
 */
abstract class TemplateProvider
{
    /** @var string Default control bucket name for template test. */
    protected const TEMPLATE_CONTROL = 'control';

    /**
     * Get the requested template.
     * @param string $context The context under which to look for a template.
     * @param string $name The name of the template.
     * @param string|null $component The requested component.
     * @throws TemplateNotFoundException If the requested template could not be found or opened.
     * Also can throw \JsonException If the file exists, but contains invalid JSON.
     * @return array The requested template as a deserialized array.
     */
    public static function get_template(string $context, string $name, ?string $component = null): array
    {
        $template = YamlTemplateProvider::getInstance()->getTemplate($context, $name, $component) ??
            ConfigTemplateProvider::getInstance()->getTemplate($context, $name, $component);
        if (empty($template)) {
            throw new TemplateNotFoundException(
                sprintf('Cannot read template for %s:%s', $context, $name)
            );
        }
        return $template;
    }

    /**
     * Check if the requested template exists. Does not check if it is valid! Be careful!
     * @param string $context The context under which to look for a template.
     * @param string $name The name of the template.
     * @param array $meta Other meta content to log i.e. request_id, session_id, request contents
     * @return bool True iff it exists.
     */
    public static function template_exists(string $context, string $name, ?array $meta = []): bool
    {
        try {
            $yaml_template_path = YamlTemplateProvider::getInstance()->getPathForTemplate($context, $name);
            if (is_file($yaml_template_path) && is_readable($yaml_template_path)) {
                return true;
            }
        } catch (\InvalidArgumentException $iae) {
            // if the context or name are invalid, continue to try config repo templates:
        }
        try {
            $extra = ['template_name' => $name] + $meta;
            $config_template_path = ConfigTemplateProvider::getInstance()->getPathForTemplate($context, $name);
        } catch (\InvalidArgumentException | TemplateNotFoundException $iae) {
            // if the context or name are invalid, it does not exist:
            StreamBuilder::getDependencyBag()->getLog()->exception(new TemplateNotFoundException(), $context, $extra);
            return false;
        }
        $found = (is_file($config_template_path) && is_readable($config_template_path));
        if (!$found) {
            StreamBuilder::getDependencyBag()->getLog()->exception(new TemplateNotFoundException(), $context, $extra);
        }
        return $found;
    }

    /**
     * List template names in the given context
     * @param string $context The context under which to list templates.
     * @return string[] Template names within the requested context.
     */
    public static function list_templates(string $context): array
    {
        $templates = [];
        if (in_array($context, ConfigTemplateProvider::getInstance()->listContexts(), true)) {
            $templates = array_merge($templates, static::list_templates_rec(
                ConfigTemplateProvider::getInstance()->getPathForContext($context),
                '',
                '.json'
            ));
        }
        if (in_array($context, YamlTemplateProvider::getInstance()->listContexts(), true)) {
            $templates = array_merge($templates, static::list_templates_rec(
                YamlTemplateProvider::getInstance()->getPathForContext($context),
                '',
                '.yml'
            ));
        }
        return array_unique($templates);
    }

    /**
     * Recursive template collector. Converts paths back into template names, by concatenating folder names with periods
     * and stripping off the trailing extension of found files.
     * @param string $abs_path The path so far.
     * @param string $name_prefix The name so far.
     * @param string $name_suffix File extension like '.json' or '.yml'
     * @return string[] The templates found in this path and all subpaths.
     */
    private static function list_templates_rec(string $abs_path, string $name_prefix, string $name_suffix): array
    {
        if (!(is_dir($abs_path) && is_readable($abs_path))) {
            return [];
        }
        $names = [];
        foreach (scandir($abs_path) as $rel_subpath) {
            if (empty($rel_subpath) || $rel_subpath[0] === '.') {
                continue;
            }
            $abs_subpath = sprintf('%s/%s', $abs_path, $rel_subpath);
            if (
                is_file($abs_subpath) &&
                is_readable($abs_subpath) &&
                substr($rel_subpath, 0 - strlen($name_suffix)) === $name_suffix
            ) {
                $names[] = sprintf('%s%s', $name_prefix, basename($rel_subpath, $name_suffix));
            }
        }
        return $names;
    }

    /**
     * List contexts names.
     * @return string[] Context names available.
     */
    public static function list_contexts(): array
    {
        $context =  array_unique(array_merge(
            ConfigTemplateProvider::getInstance()->listContexts(),
            YamlTemplateProvider::getInstance()->listContexts()
        ));
        return array_values($context);
    }

    /**
     * To map from planout output to a template name.
     * When test template name is invalid, it will tick the error and fall back to default template.
     * @param string $context The template context for the test, e.g. 'dashboard' `community_hub`
     * @param string $test_template_name The supposed to be tested template name.
     * @param string $default_template_name The default template name.
     * @return string The template name.
     */
    public static function parseTestTemplateName(
        string $context,
        string $test_template_name,
        string $default_template_name
    ): string {
        if (strcasecmp($test_template_name, static::TEMPLATE_CONTROL) === 0) {
            // Falls to control group.
            return $default_template_name;
        }
        if (
            empty($test_template_name) ||
            !(static::template_exists($context, $test_template_name))
        ) {
            // planout variant is not a valid template, fallback to default template.
            StreamBuilder::getDependencyBag()->getLog()->rateTick('streambuilder_errors', "${context}_bad_test_template_name");
            return $default_template_name;
        }
        return $test_template_name;
    }

    /**
     * Resolve the first met component according to the requested component name along the template tree.
     * @param array $template Template
     * @param string|null $component The requested component.
     * @return array
     */
    protected function resolveComponent(array $template, ?string $component = null): array
    {
        // early termination if no requested component
        if (empty($component)) {
            return $template;
        }

        // found the request component
        if (($template[StreamContext::COMPONENT_NAME] ?? '') === $component) {
            return $template;
        }
        // recursively traverse
        foreach ($template as $v) {
            if (is_array($v)) {
                $resolved = $this->resolveComponent($v, $component);
                if (!empty($resolved)) {
                    return $resolved;
                }
            }
        }
        return [];
    }

    /**
     * Get the requested template.
     * @param string $context The context under which to look for a template.
     * @param string $name The name of the template.
     * @param string|null $component The requested component.
     * @throws TemplateNotFoundException If the requested template could not be found or opened.
     * Also can throw \JsonException If the file exists, but contains invalid JSON.
     * @return array|null The requested template as an array.
     */
    abstract protected function getTemplate(string $context, string $name, ?string $component = null): ?array;

    /**
     * Get the local-filesystem path to the directory containing templates for the requested context.
     * @param string $context The context for which to return the directory.
     * @throws \InvalidArgumentException If the context is illegal.
     * @return string The path to the directory containing templates for the requested context.
     */
    abstract protected function getPathForContext(string $context): string;

    /**
     * Get the local-filesystem path to the template of requested context.
     * @param string $context The context for which to return the directory.
     * @param string $template_name Template name.
     * @throws \InvalidArgumentException If the context is illegal.
     * @return string The path to the directory containing templates for the requested context.
     */
    abstract protected function getPathForTemplate(string $context, string $template_name): string;

    /**
     * List contexts names.
     * @return string[] Context names available.
     */
    abstract protected function listContexts(): array;
}
