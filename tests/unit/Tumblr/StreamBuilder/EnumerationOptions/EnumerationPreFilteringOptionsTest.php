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

use Tumblr\StreamBuilder\EnumerationOptions\EnumerationPreFilteringOptions;
use function array_keys;

class EnumerationPreFilteringOptionsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getter to return ids to filter
     */
    public function test_get_ids_to_filter()
    {
        $input = array_keys([123 => 10, 456 => 11, 789 => 12]);
        $enumeration_options = new EnumerationPreFilteringOptions($input, null, null);
        $this->assertSame($input, $enumeration_options->get_ids_to_filter());
    }
}
