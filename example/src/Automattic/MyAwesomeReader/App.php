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

namespace Automattic\MyAwesomeReader;

use Automattic\MyAwesomeReader\StreamBuilder\Trending\Streams\TrendingTopicStream;
use Tumblr\StreamBuilder\StreamBuilder;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamSerializer;
use Tumblr\StreamBuilder\TemplateProvider;

/**
 * The main application class.
 */
class App
{
    /**
     * Simulates an endpoint where you get the trending posts.
     * @return StreamElement[]
     */
    public function getTrendingPosts(): array
    {
        return (new TrendingTopicStream('trending'))->enumerate(2)->get_elements();
    }

    /**
     * Simulates an endpoint where you get the trending posts from a template.
     * @param StreamCursor|null $cursor A specific cursor to use, or first "page" if null.
     * @return StreamElement[]
     */
    public function getTrendingPostsFromTemplate(?StreamCursor $cursor = null): array
    {
        $template = 'awesome_trending.20230615';
        $meta = [];
        $stream = StreamSerializer::from_template(new StreamContext(
            TemplateProvider::get_template('trending', $template),
            $meta,
            StreamBuilder::getDependencyBag()->getCacheProvider(),
            $template
        ));
        return $stream->enumerate(2, $cursor)->get_elements();
    }
}
