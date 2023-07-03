<?php

namespace TumblrExtras\GitDiff\Differs;

use TumblrExtras\GitDiff\GithubApi;

/**
 * Class PullRequestDiffer
 * Uses the Github API on a Pull Request to get diff information
 */
class PullRequestDiffer implements DifferInterface
{
    /** @var GithubApi Reference to Github API helper */
    private $github_api;

    /**
     * PullRequestDiffer constructor.
     * @param string $gh_host Github API host and possibly some path parameters
     * @param string $gh_owner Github owner (github org or username)
     * @param string $gh_repo Github repo name
     * @param string $gh_auth Auth token
     * @param bool $gh_debug Debugging mode
     */
    public function __construct(
        string $gh_host,
        string $gh_owner,
        string $gh_repo,
        string $gh_auth,
        bool $gh_debug = false
    ) {
        $this->github_api = new GithubApi(
            $gh_host,
            $gh_owner,
            $gh_repo,
            '', // We don't have the PR number yet
            $gh_auth,
            $gh_debug
        );
    }

    /**
     * @inheritDoc
     */
    public function getDiffOutput(): string
    {
        $headers = [
            'Accept' => 'application/vnd.github.v3.diff',
        ];
        $response = $this->github_api
            ->withHeaders($headers)
            ->sendRequest(
                'GET',
                'pulls',
                [],
                ''
            );

        return $response;
    }

    /**
     * Get PR info for a branch
     * @param string $branch Branch name for which to look up the pull request
     * @return array hash of pr_number, head_sha, base_sha
     */
    public function getPullRequestInfo(string $branch): array
    {
        return $this->github_api->getPullRequestInfo($branch);
    }

    /**
     * Set the pull request number to use on subsequent requests
     * @param int $number Pull request number
     */
    public function setPullRequestNumber(int $number)
    {
        $this->github_api->setPullRequestNumber($number);
    }
}
