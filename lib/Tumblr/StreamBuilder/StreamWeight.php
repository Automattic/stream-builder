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

use Tumblr\StreamBuilder\Streams\Stream;
use function abs;
use function floatval;
use function get_class;

/**
 * Struct containing a Stream and Weight.
 */
final class StreamWeight extends Templatable
{
    /** @var Stream */
    private $stream;
    /** @var float */
    private $weight;

    /**
     * @param float $weight The float indicates the weight of a stream in a mixer.
     * @param Stream $stream The inner stream wrapped by a weight.
     */
    public function __construct(float $weight, Stream $stream)
    {
        $this->stream = $stream;
        $this->weight = abs(floatval($weight));
    }

    /** @return Stream */
    public function get_stream(): Stream
    {
        return $this->stream;
    }

    /** @return float */
    public function get_weight(): float
    {
        return $this->weight;
    }

    /**
     * @inheritDoc
     */
    public function to_template(): array
    {
        return [
            "_type" => get_class($this),
            "weight" => $this->get_weight(),
            "stream" => $this->get_stream()->to_template(),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context): self
    {
        $stream_weight = $context->get_optional_property('weight');
        $stream = $context->deserialize_required_property('stream');
        return new self($stream_weight, $stream);
    }
}
