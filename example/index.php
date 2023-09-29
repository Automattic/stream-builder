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

// require our app bootstrap, which includes StreamBuilder
require_once __DIR__ . '/src/Automattic/MyAwesomeReader/bootstrap.php';

// init our application
$app = new \Automattic\MyAwesomeReader\App();

// check to see if we have a cursor for pagination
$cursor = $_REQUEST['cursor'] ?? null;
if (is_string($cursor)) {
    $cursor = \Tumblr\StreamBuilder\StreamCursors\StreamCursor::decode($cursor, ['secret'], ['key']);
} else {
    $cursor = null;
}

echo '<html><body><h1>StreamBuilder Example Output:</h1><div><pre>';
// use our example StreamBuilder implementation!
$results = $app->getTrendingPostsFromTemplate($cursor);
print_r($results);
echo '</pre></div>';

if ($results) {
    // link to the next "page" with StreamBuilder's cursor-based pagination
    $last_cursor = end($results)->get_cursor();
    $next_cursor_string = \Tumblr\StreamBuilder\StreamCursors\StreamCursor::encode($last_cursor, 'secret', 'key');
    echo '<div><a href="/?cursor=' . $next_cursor_string . '">Next page!</a></div>';
}
echo '</body></html>';
