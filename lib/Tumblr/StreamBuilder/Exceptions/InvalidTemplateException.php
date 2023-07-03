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

use Tumblr\StreamBuilder\Codec\Codec;
use Tumblr\StreamBuilder\Helpers;

/**
 * This exception is thrown when we are unable to decode from a byte string to get a Templatable object in a @Codec.
 */
final class InvalidTemplateException extends \RuntimeException
{
    /**
     * Signature doesn't match when we decode.
     * @var int
     */
    public const TYPE_SIGNATURE_MISMATCH = 1;

    /**
     * Invalid json string to parse when we decode.
     * @var int
     */
    public const TYPE_INVALID_JSON = 2;

    /**
     * Invalid template array to deserialize when we decode.
     * @var int
     */
    public const TYPE_INVALID_TEMPLATE = 3;

    /**
     * Necessary components to decode are missing, cannot process forward.
     * @var int
     */
    public const TYPE_MISSING_COMPONENT = 4;

    /**
     * Can not inflate a deflated string.
     * @var int
     */
    public const TYPE_INVALID_DEFLATION = 5;

    /**
     * The integer indicator of what types of error.
     * @var int
     */
    private $type;

    /**
     * InvalidTemplateException constructor.
     * @param string $message The exception message.
     * @param Codec $codec The codec that failed to decode a byte string.
     * @param int $type The exception type, see constants in this class.
     */
    public function __construct(string $message, Codec $codec, int $type)
    {
        $message = sprintf(
            'Unable to decode with Codec(%s): %s',
            Helpers::get_unqualified_class_name($codec),
            $message
        );
        $this->type = $type;
        parent::__construct($message);
    }

    /**
     * To get exception type.
     * @return int
     */
    public function get_type(): int
    {
        return $this->type;
    }
}
