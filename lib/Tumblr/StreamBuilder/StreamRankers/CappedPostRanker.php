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

namespace Tumblr\StreamBuilder\StreamRankers;

use Tumblr\StreamBuilder\Interfaces\PostStreamElementInterface;
use Tumblr\StreamBuilder\Interfaces\User;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;

/**
 * This ranker has two main goals:
 * 1. Act as a safety mechanism for any perturbation we do in ranking and
 * 2. Apply some fairness
 *
 * When it comes to 1, since the perturbation is working properly we shouldn't be expecting to change much but primarily
 * act as a safety net. For the second point, we are fighting the rich get richer premise, where popular posts dominate
 * feeds and attract even more engagement (and are then ranked higher because we are optimizing for this) and as a
 * result posts with less engagement appear at the bottom of the feed. With the capped ranker we are capping the presence
 * of popular blogs to a configurable number (i.e. cap) and giving the chance to some other post to shine.
 */
class CappedPostRanker extends StreamRanker
{
    /** @var User User */
    private User $user;

    /** @var bool|null debug */
    private ?bool $debug;

    /** @var bool cap_desc */
    private bool $cap_desc;

    /** @var int cap */
    private int $cap;

    /** @var string ranking_context */
    private string $ranking_context;

    /** @var bool panel_allow_ranking Whether the meta key on panel regarding ranking is enabled or not */
    private bool $panel_allow_ranking;

    /**
     * CappedPostRanker constructor.
     * @param User $user User
     * @param string $identity The identity of this ranker
     * @param bool $cap_desc Whether we are looking for the first violated blog post in descending order (top-down)
     * @param int $cap The cap applied (higher values are making the reranking less strict)
     * @param string $ranking_context The context that ranking is being applied to e.g. dashboard
     * @param bool $panel_allow_ranking Whether the meta key on panel regarding ranking is enabled or not
     * @param bool $debug Need extra debug infos or not. e.g. Ranking score
     */
    public function __construct(
        User $user,
        string $identity,
        bool $cap_desc,
        int $cap,
        string $ranking_context,
        bool $panel_allow_ranking,
        ?bool $debug = null
    ) {
        parent::__construct($identity);
        $this->user = $user;
        $this->debug = $debug;
        $this->cap_desc = $cap_desc;
        $this->cap = $cap;
        $this->ranking_context = $ranking_context;
        $this->panel_allow_ranking = $panel_allow_ranking;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function rank_inner(array $stream_elements, ?StreamTracer $tracer = null): array
    {
        if ($this->can_rank()) {
            return $this->apply_capped_reranking($stream_elements, $this->debug, $this->cap_desc, $this->cap);
        }
        return $stream_elements;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return ['_type' => get_class($this)];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_meta_by_key('user'),
            $context->get_current_identity(),
            $context->get_optional_property('cap_desc', true),
            $context->get_optional_property('cap', 2),
            $context->get_optional_property('ranking_context', 'dashboard'),
            $context->get_meta_by_key('allow_ranking'),
            $context->get_meta_by_key('client_meta')->is_panel()
        );
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function pre_fetch(array $elements)
    {
        foreach ($elements as $element) {
            $original_element = $element->get_original_element();
            if (!is_subclass_of($original_element, PostStreamElementInterface::class)) {
                throw new TypeMismatchException(PostStreamElementInterface::class, $element);
            }
        }
    }

    /**
     * Enables or disables ranking depending on the context. When used in the feed for example, we want to only
     * rank when best stuff first are enabled (i.e. ranking is enabled)
     * @return bool Whether the ranker is allowed to rank or return the identity of the initial results
     */
    public function can_rank(): bool
    {
        if ($this->debug) {
            return $this->panel_allow_ranking;
        }
        if ($this->ranking_context === 'dashboard') {
            return $this->user->isFeedRankingEnabled();
        }
        return true;
    }

    /**
     * Applies the capped reranking algorithm
     * @param array $elements The list of ranked elements
     * @param bool $debug Whether to add debugging info
     * @param bool $cap_desc Whether we are looking for the first violated blog post in descending order (top-down)
     * @param int $cap The cap applied (higher values are making the reranking less strict)
     * @return array The reranked elements where the capped reranking has been applied
     */
    private function apply_capped_reranking(array $elements, bool $debug, bool $cap_desc, int $cap): array
    {
        $ranked_elements = [];
        $post_added = [];
        $caps_applied = 0;
        $blog_dictionaries = $this->get_blog_dictionaries($elements);
        $post_to_element = $blog_dictionaries[0];
        $blog_to_posts_ids = $blog_dictionaries[1];
        $blog_to_stats = $blog_dictionaries[2];
        foreach ($elements as $rank => $post) {
            /** @var PostStreamElementInterface $original_elem */
            $original_elem = $post->get_original_element();
            $current_blog_id = $original_elem->getBlogId();
            $current_post_id = $original_elem->getPostId();
            // We've added this before, move on
            if (array_key_exists($current_post_id, $post_added)) {
                continue;
            }
            $violated_blog_id = $this->get_violated_blog_id(
                $cap,
                $current_blog_id,
                $rank,
                $post_added,
                $elements,
                $blog_to_stats,
                $cap_desc
            );
            if (is_null($violated_blog_id)) {
                // No violation: 1. Copy element to new list of ranked elements and 2. Increase num of shown posts
                array_push($ranked_elements, $post);
                $post_added[$current_post_id] = true;
                $this->invalidate_post_id($current_blog_id, $current_post_id, $blog_to_posts_ids);
                $this->bump_seen_posts($blog_to_stats, $current_blog_id);
            } else {
                // Violation: 1. Fetch and add the violated post instead, 2. Save current post for later
                $violated_post_id = $this->invalidate_post_id($violated_blog_id, null, $blog_to_posts_ids);
                $post_added[$violated_post_id] = true;
                $this->bump_seen_posts($blog_to_stats, $violated_blog_id);
                if ($debug) {
                    $post_to_element[$violated_post_id]->add_debug_info('capped_reranking', 'cap_replacement', true);
                    $post_to_element[$violated_post_id]->add_debug_info(
                        'capped_reranking',
                        'seeing_this_instead_of',
                        strval($current_post_id)
                    );
                    $post_to_element[$violated_post_id]->add_debug_info(
                        'capped_reranking',
                        'blog_total_posts',
                        $blog_to_stats[$violated_blog_id][0]
                    );
                    $post_to_element[$violated_post_id]->add_debug_info(
                        'capped_reranking',
                        'blog_shown_so_far',
                        $blog_to_stats[$violated_blog_id][1]
                    );
                }
                array_push($ranked_elements, $post_to_element[$violated_post_id]);
                // Add the violator
                array_push($ranked_elements, $post_to_element[$current_post_id]);
                $post_added[$current_post_id] = true;
                $this->invalidate_post_id($current_blog_id, $current_post_id, $blog_to_posts_ids);
                $this->bump_seen_posts($blog_to_stats, $current_blog_id);
                if ($debug) {
                    $post_to_element[$current_post_id]->add_debug_info('capped_reranking', 'capped', true);
                    $post_to_element[$current_post_id]->add_debug_info(
                        'capped_reranking',
                        'capped_by',
                        strval($violated_post_id)
                    );
                    $post_to_element[$current_post_id]->add_debug_info(
                        'capped_reranking',
                        'blog_total_posts',
                        $blog_to_stats[$current_blog_id][0]
                    );
                    $post_to_element[$current_post_id]->add_debug_info(
                        'capped_reranking',
                        'blog_shown_so_far',
                        $blog_to_stats[$current_blog_id][1]
                    );
                }
                $caps_applied++;
            }
        }
        return $ranked_elements;
    }

    /**
     * Invalidates the first available post from a given blog and returns the violated post ID if found
     * @param string $blog_id The blog ID
     * @param string|null $post_id If this is set then there is a specific post ID that we want to find and invalidate
     * alternatively we need to look for the first available post
     * @param array $blog_to_posts_ids A dictionary mapping blogs to their post IDs
     * @return ?int The post ID or none
     */
    private function invalidate_post_id(string $blog_id, ?string $post_id, array &$blog_to_posts_ids): ?int
    {
        $posts_ids_for_blog = $blog_to_posts_ids[$blog_id];
        $violated_post_id = null;
        $post_index = -1;
        if (isset($post_id)) {
            if (($key = array_search($post_id, $posts_ids_for_blog)) !== false) {
                $post_index = $key;
            }
        } else {
            $violated_post_id = current($posts_ids_for_blog);
            if (($key = array_search($violated_post_id, $posts_ids_for_blog)) !== false) {
                $post_index = $key;
            }
        }

        // Unset post
        unset($posts_ids_for_blog[$post_index]);
        // Put array of posts back
        $blog_to_posts_ids[$blog_id] = $posts_ids_for_blog;

        return $violated_post_id;
    }

    /**
     * Bumps seen posts which are found within the stats of a blog
     * @param array $blog_to_stats The blog to statistics dictionary
     * @param string $blog_id The blog ID
     * @return void
     */
    private function bump_seen_posts(array &$blog_to_stats, $blog_id)
    {
        $x = $blog_to_stats[$blog_id][0];
        $y = $blog_to_stats[$blog_id][1];
        $blog_to_stats[$blog_id] = [$x, $y + 1];
    }

    /**
     * Given the frequency dictionary for all blogs and a current blog's number of displayed post
     * it returns the post ID of a blog that has been potentially violated.
     * For a given blog B which shows its ith post, unfairness occurs when there is a blog L with stats [X, Y]
     * where X > Y (hasn't shown all posts) AND Y < i (unfairness)
     * @param int $cap The applied cap
     * @param string $examined_blog_id The current blog's ID examined for violation
     * @param int $rank Current rank so that we can search from there onwards
     * @param array $post_added A list of posts that we've seen already
     * @param array $ranked_elements The array of ranked elements as returned from the ranker
     * @param array $blog_to_stats Blog ID to number of available posts and number of posts shown
     * @param bool $cap_desc Whether we are looking for the first violated blog post in descending order (top-down)
     * @return string|null The violated Blog ID
     */
    private function get_violated_blog_id(
        int $cap,
        string $examined_blog_id,
        int $rank,
        array $post_added,
        array $ranked_elements,
        array $blog_to_stats,
        bool $cap_desc
    ): ?int {
        $violated_blog_id = null;
        // If I increase the examined/violator blog's displayed posts by one, is there a violation?
        $current_blog_displayed_posts = $blog_to_stats[$examined_blog_id][1] + 1;
        $ranked_elements_slice = array_slice($ranked_elements, $rank);
        if ($cap_desc) {
            $ranked_elements_slice = array_reverse($ranked_elements_slice);
        }
        foreach ($ranked_elements_slice as $ranked_element) {
            /** @var PostStreamElementInterface $original_element */
            $original_element = $ranked_element->get_original_element();
            $blog_id = $original_element->getBlogId();

            $post_stats = $blog_to_stats[$blog_id];
            $x = $post_stats[0];
            $y = $post_stats[1];
            // Violation
            if ($x > $y && $y < $current_blog_displayed_posts - $cap) {
                $violated_blog_id = $blog_id;
            }
        }
        return $violated_blog_id;
    }

    /**
     * Iterates through the ranked elements and constructs a frequency dictionary which maps each blog ID
     * to an array with two numbers: a. the number of posts that this blog has and b. the number of posts that it
     * has already shown while iterating the wholle ranked list. Initially b will be initialized to zero.
     * At the expense of single responsibility it also populates a post ID to the derived element
     * dictionary, since we are iterating.
     *
     * @param array $elements The list of elements
     * @return array An array which contains dictionaries which map:
     * 1. post IDs to the original element
     * 2. blog IDs to the original element
     * 3. blog IDs to [#posts, #appearances_so_far]
     */
    private function get_blog_dictionaries(array $elements)
    {
        $post_to_element = [];
        $blog_to_posts_ids = [];
        $stats_per_blog = [];
        foreach ($elements as $post) {
            /** @var PostStreamElementInterface $original_elem */
            $original_elem = $post->get_original_element();
            $blog_id = $original_elem->getBlogId();
            $post_id = $original_elem->getPostId();
            // Post to element
            $post_to_element[$post_id] = $post;
            // Blog to element
            if (!isset($blog_to_posts_ids[$blog_id])) {
                $blog_to_posts_ids[$blog_id] = [$post_id];
            } else {
                $blog_posts_array = $blog_to_posts_ids[$blog_id];
                array_push($blog_posts_array, $post_id);
                $blog_to_posts_ids[$blog_id] = $blog_posts_array;
            }
            // Blog to stats
            $current_blog_stats = [];
            if (!isset($stats_per_blog[$blog_id])) {
                $current_blog_stats = [1, 0];
            } else {
                $current_blog_stats = [$stats_per_blog[$blog_id][0] + 1, 0];
            }
            $stats_per_blog[$blog_id] = $current_blog_stats;
        }
        return [$post_to_element, $blog_to_posts_ids, $stats_per_blog];
    }
}
