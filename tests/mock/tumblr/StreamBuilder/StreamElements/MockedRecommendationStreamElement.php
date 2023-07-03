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

namespace Tests\Mock\Tumblr\StreamBuilder\StreamElements;

use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\RecommendationLeafStreamElementTrait;

/**
 * A mocked version of the RecommendationStreamElement.
 */
class MockedRecommendationStreamElement extends LeafStreamElement
{
    use RecommendationLeafStreamElementTrait;

    /**
     * @var int The blog id.
     */
    private int $blog_id;

    /**
     * @param int $blog_id The blog id.
     * @param float $score The score.
     */
    public function __construct(int $blog_id, float $score)
    {
        $this->blog_id = $blog_id;
        $this->score = $score;
        parent::__construct('any_identity');
    }


    /**
     * @inheritDoc
     */
    public function get_cache_key()
    {
    }

    /**
     * @inheritDoc
     */
    protected function to_string(): string
    {
        return 'MockedRecommendationStreamElement';
    }

    /**
     * @inheritDoc
     */
    public static function from_template(StreamContext $context)
    {
    }

    /**
     * @return int The blog id.
     */
    final public function get_blog_id(): int
    {
        return $this->blog_id;
    }
}
