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

namespace Tumblr\StreamBuilder\StreamTracers;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\Identifiable;
use function sprintf;
use function date;
use function is_resource;
use function fwrite;

/**
 * A tracer which saves debugging info to an output array.
 */
final class DebugStreamTracer extends StreamTracer
{
    /** @var array string */
    private $output = [];

    /** @var resource|null */
    private $stream;

    /**
     * @param resource|null $log_filehandle The stream to log to, if NULL (the default) will just record
     * into an array. If provided, will record into the array AND echo lines as they are generated
     * to the provided stream (you can pass `STDOUT` or `STDERR`to this parameter - they
     * are predefined constants of type `resource`)
     */
    public function __construct($log_filehandle = null)
    {
        $this->stream = $log_filehandle;
    }

    /**
     * Get standard output string
     * @return array debugging output
     */
    public function get_output()
    {
        return $this->output;
    }

    /**
     * @inheritDoc
     */
    public function trace_event(
        string $event_category,
        Identifiable $sender,
        string $event_name,
        ?array $timing = [],
        ?array $meta = []
    ): void {
        $message = sprintf(
            'op=%s sender=%s status=%s%s other=%s',
            $event_category,
            $sender->get_identity(true),
            $event_name,
            !empty($timing) ? sprintf(' start_time=%s duration=%s', $timing[0], $timing[1]) : '',
            Helpers::json_encode($meta)
        );
        $log_string = sprintf('[%s]: %s', date(\DateTime::ATOM), $message);
        $this->output[] = $log_string;
        if (is_resource($this->stream)) {
            fwrite($this->stream, $log_string);
        }
    }
}
