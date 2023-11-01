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

use Tumblr\StreamBuilder\Exceptions\InvalidStreamArrayException;

/**
 * Provide stream serialization and deserialization service.
 * Use public static methods "to_template" and "from_template" to transform between stream template and objects.
 * Template array description can be found in Templatable.
 */
final class StreamSerializer
{
    /**
     * Convert a stream object to a template.
     * @param Templatable $stream The stream object need to be serialized.
     * @return array The serialized template.
     */
    public static function to_template(Templatable $stream): array
    {
        $template = $stream->to_template();
        if ($stream->getComponent() != null) {
            $template[StreamContext::COMPONENT_NAME] = $stream->getComponent();
        }
        return $template;
    }

    /**
     * Convert a template to a stream object.
     * @param StreamContext $context The context stores the stream template and other necessary data.
     * @throws InvalidStreamArrayException If input json is not valid.
     * @return mixed The object extends Templatable
     */
    public static function from_template(StreamContext $context): Templatable
    {
        $template = $context->get_template();
        if (empty($template) || !array_key_exists('_type', $template) || !method_exists($template['_type'], 'from_template')) {
            throw new InvalidStreamArrayException($template);
        }
        /** @var Templatable $object */
        $object = call_user_func([$template['_type'], 'from_template'], $context);
        $component = $template[StreamContext::COMPONENT_NAME] ?? null;
        $object->setComponent($component);
        $skip_components = $context->get_meta_by_key(StreamContext::SKIP_COMPONENT_META) ?? [];
        if (in_array($component, $skip_components, true)) {
            $object->setSkippedComponent(true);
        }
        return $object;
    }
}
