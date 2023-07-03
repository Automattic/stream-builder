<?php

namespace TumblrExtras\phpcs\Outputters;

use TumblrExtras\GitDiff\GithubApi;

/**
 * Class OutputterStdout
 *
 * The Github outputter implementation, writes to a github PR
 *
 */
class OutputterGithub implements OutputterInterface
{
    /**
     * The github host url
     *
     * @var string
     */
    protected $github_url;

    /**
     * The repo owner
     *
     * @var string
     */
    protected $owner;

    /**
     * The repository
     *
     * @var string
     */
    protected $repository;

    /**
     * The pull request ID
     *
     * @var
     */
    protected $pr_id;

    /**
     * The OAuth token
     *
     * @var
     */
    protected $oauth_token;

    /**
     * The existing comments for the PR
     *
     * @var array
     */
    protected $comments;

    /**
     * Output debug information
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Buffer of messages to be sent
     *
     * @var array
     */
    private $messages_buffer = [];

    /** @var GithubApi Reference to Github API helper */
    private $github_api;

    /**
     * @param $github_url
     * @param $owner
     * @param $repository
     * @param $pr_id
     * @param $oauth_token
     * @param bool $debug
     */
    public function __construct($github_url, $owner, $repository, $pr_id, $oauth_token, $debug = false)
    {
        $this->github_api = new GithubApi(
            $github_url,
            $owner,
            $repository,
            $pr_id,
            $oauth_token,
            $debug
        );

        $this->get_comments();
    }

    /**
     * Gets the handle for the outputter
     *
     * @return string
     */
    public function get_handle()
    {
        return 'github';
    }

    /**
     * Gets existing PR comments indexed by diff position
     *
     * @return array
     */
    protected function get_comments()
    {
        if (isset($this->comments)) {
            return $this->comments;
        }

        try{
            $pr_comments = $this->get_github_api()->sendRequest('GET', 'pulls');
            $comments = array();
            foreach ($pr_comments as $comment) {
                if (!isset($comment->position) || !$comment->position || !isset($comment->body) || !isset($comment->path)) {
                    continue;
                }

                if (!isset($this->comments[$comment->path])) {
                    $this->comments[$comment->path] = array();
                }

                if (!isset($comments[$comment->path][$comment->position])) {
                    $comments[$comment->path][$comment->position] = array();
                }

                $comments[$comment->path][$comment->position][] = $comment;
            }
        } catch (\Exception $e) {
            error_log('Exception occurred when trying to get comments on a PR: ' . $e->getMessage());
        }

        $this->comments = $comments;

        return $this->comments;
    }

    /**
     * @inheritDoc
     */
    public function write($message, array $meta = array(), bool $buffer = true)
    {
        if (isset($meta['target_commit'], $meta['file'], $meta['diff_line'])) {

            foreach (array('target_commit' => 'commit_id', 'file' => 'path', 'diff_line' => 'position') as $key => $value) {
                $data[$value] = $meta[$key];
            }

            $comments = isset($this->comments[$data['path']][$data['position']]) ? $this->comments[$data['path']][$data['position']] : array();

            // Iterate errors, compile message and check if message exists already
            $compile = function ($errors, $type) use ($comments) {
                $messages = array();

                foreach ($errors as $error) {

                    $message = trim(sprintf('**%s** : %s %s', $type, $error['message'], ($error['fixable'] ? '**[fixable]**' : '')));
                    $has_message = false;

                    // Check each existing comment for a match
                    foreach ($comments as $comment) {

                        $body = isset($comment->body) ? trim($comment->body) : null;

                        // Check for existing comment on the same line, suppress if matched
                        if ($comment && $body && ($body === $message || stripos($body, $message) !== false)) {
                            $has_message = true;
                            break;
                        }
                    }

                    if ($has_message) {
                        continue;
                    }

                    $messages[] = $message;
                }

                return implode(PHP_EOL, $messages);
            };

            if (isset($meta['errors']) && !empty($meta['errors'])) {
                $message .= $message ? PHP_EOL : '';
                $message .= $compile($meta['errors'], 'ERROR');
            }

            if (isset($meta['warnings']) && !empty($meta['warnings'])) {
                $message .= $message ? PHP_EOL : '';
                $message .= $compile($meta['warnings'], 'WARNING');
            }

            $message = trim($message);

            if (!$message) {
                return null;
            }

            $data['body'] = $message;

            if ($buffer) {
                return $this->messages_buffer[] = ['POST', 'pulls', $data];
            }

            return $this->get_github_api()->sendRequest('POST', 'pulls', $data);
        }

        $data = array('body' => $message, 'commit_id' => $meta['commit_id'] ?? null);

        if ($buffer) {
            return $this->messages_buffer[] = ['POST', 'issues', $data];
        }

        return $this->get_github_api()->sendRequest('POST', 'issues', $data);
    }

    /**
     * @return GithubApi Exposes the Github API reference in case it needs to be used.
     */
    public function get_github_api()
    {
        return $this->github_api;
    }

    /**
     * Sends the batched messages, optionally combining into a Github review, for messages grouped by the same
     * commit_id.
     * @param bool $send_reviews If true, all messages for the same commit_id are grouped into a review
     * @param string $event The github event to send for a review
     * @return void
     */
    public function send_batched(bool $send_reviews = true, string $event = 'REQUEST_CHANGES')
    {
        $reviews = [];

        foreach ($this->messages_buffer as $message) {
            list($method, $type, $data) = $message;

            // If no commit id or not sending reviews, just send the request as is
            $commit_id = $data['commit_id'] ?? null;
            if (!$send_reviews || !$commit_id) {
                $this->get_github_api()->sendRequest($method, $type, $data);
                continue;
            }

            // Create data holder for review
            if (!isset($reviews[$commit_id])) {
                $reviews[$commit_id] = [
                    'commit_id' => $commit_id,
                    'body' => '',
                    'event' => $event,
                    'comments' => [],
                ];
            }

            unset($data['commit_id']);

            // If path, position & body exist, it's a line specific commit, else append message onto review body
            if (!empty($data['path']) && !empty($data['position']) && !empty($data['body'])) {
                $reviews[$commit_id]['comments'][] = [
                    'path' => $data['path'],
                    'position' => $data['position'],
                    'body' => $data['body'],
                ];
            } else {
                $reviews[$commit_id]['body'] = trim($reviews[$commit_id]['body'] . PHP_EOL . $data['body'], PHP_EOL);
            }

        }

        // Send each review
        foreach ($reviews as $review) {
            $this->get_github_api()->sendRequest('POST', 'pulls', $review, 'reviews');
        }
    }
}
