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
 * Throw this exception when a stream builder template file is not found on disk.
 * It usually means a user is trying to visit a dashboard template that's not supported anymore.
 */
class TemplateNotFoundException extends \InvalidArgumentException
{
    /**
     * TemplateNotFoundException constructor.
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param \Throwable $previous The previous exception.
     */
    public function __construct($message = "Template not found", $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
