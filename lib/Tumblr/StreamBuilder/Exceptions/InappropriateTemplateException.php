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

namespace Tumblr\StreamBuilder\Exceptions;

/**
 * Class InappropriateTemplateException
 * When the decode output is not a desired Template object.
 */
class InappropriateTemplateException extends \RuntimeException
{
    /**
     * InappropriateTemplateException constructor.
     * @param string $desired The desired Templatable object class.
     * @param string $found The decoded Template object class.
     */
    public function __construct(string $desired, string $found)
    {
        parent::__construct(sprintf('Looking for %s, but %s found.', $desired, $found));
    }
}
