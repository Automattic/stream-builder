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

use Tests\Unit\Tumblr\StreamBuilder\DependencyBagTest;
use Tumblr\StreamBuilder\StreamBuilder;

// Ensure we use the same timezone as production
date_default_timezone_set('America/New_York');

// dissallow stream wrappers for some protocols
foreach (['http', 'https', 'ftp'] as $protocol) {
    if (in_array($protocol, stream_get_wrappers(), true)) {
        stream_wrapper_unregister($protocol);
    }
}


if (mb_internal_encoding() !== "ISO-8859-1") {
    echo "INCORRECT ENCODING DETECTED; overriding to ISO-8859-1\n";
    mb_internal_encoding("ISO-8859-1");
}

set_time_limit(600);
restore_error_handler();
restore_exception_handler();

require_once __DIR__ . '/../../lib/Tumblr/StreamBuilder/Constants.php';
const BASE_PATH = __DIR__;

require_once BASE_PATH . '/../../vendor/autoload.php';

// Allow CONFIG_DIR environment variable, or attempt to use relative config path
$config_dir = $_SERVER['CONFIG_DIR'] ?? null;
if (!$config_dir) {
    $config_dir = __DIR__ . '/../../../config';
}

/** @var string $config_dir Path to $CONFIG repo root dir */
define('CONFIG_DIR', is_dir($config_dir) ? realpath($config_dir) : $config_dir);

StreamBuilder::init(DependencyBagTest::retrieveDependencyBag());

// cleanup after ourselves
unset($config_dir);
