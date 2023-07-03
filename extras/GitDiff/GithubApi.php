<?php

namespace TumblrExtras\GitDiff;

/**
 * Class GithubApi
 * Helper class for interacting with the Github HTTP REST api
 */
class GithubApi
{
    /**
     * The github host domain & possibly some path prefix
     *
     * @var string
     */
    protected $github_domain;

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
     * @var int|string
     */
    protected $pr_id;

    /**
     * The OAuth token
     *
     * @var string
     */
    protected $oauth_token;

    /** @var bool Debug mode */
    private $debug = false;

    /** @var array Key=>Value pair of custom headers to send */
    private $custom_headers = [];

    /** @var bool If true, continues auto paginating until exhausted. If false, grabs only first page (or page specified) */
    private $fetch_multiple_pages = true;

    /**
     * @param $github_domain
     * @param $owner
     * @param $repository
     * @param $pr_id
     * @param $oauth_token
     */
    public function __construct($github_domain, $owner, $repository, $pr_id, $oauth_token, $debug = false)
    {
        $this->github_domain = $github_domain;
        $this->owner = $owner;
        $this->repository = $repository;
        $this->pr_id = $pr_id;
        $this->oauth_token = $oauth_token;
        $this->debug = $debug;
    }

    /**
     * Fluent way to set custom headers before sending a request
     * @param array $headers Key=>Value map of custom headers to send
     * @return GithubApi
     */
    public function withHeaders(array $headers): GithubApi
    {
        $this->custom_headers = $headers;
        return clone $this;
    }

    /**
     * Turn off querying multiple pages and return a new instance
     * @return GithubApi
     */
    public function withoutMultiplePages(): GithubApi
    {
        $new_obj = clone $this;
        $new_obj->fetch_multiple_pages = false;
        return $new_obj;
    }

    /**
     * @param int $number Pull request number
     * @return void
     */
    public function setPullRequestNumber(int $number)
    {
        if ($number < 1) {
            throw new \Exception(
                "invalid PR number provided. got '$number', expecting a value greater than 0"
            );
        }

        $this->pr_id = $number;
    }

    /**
     * Sends a message to the github API
     *
     * @param string $method    The HTTP method
     * @param string $type      The "type" to use in the URL
     * @param array  $data      Key=>Value pairs. For GET, querystring, for POST, body
     * @param string $sub_type  The "sub-type" to use in the URL, defaults to "comments"
     * @return array|mixed      The JSON decoded result (if JSON), otherwise raw text response
     * @throws \Exception
     */
    public function sendRequest($method = 'GET', $type = 'issues', $data = array(), string $sub_type = 'comments')
    {
        $method = strtoupper($method);

        $pattern = 'https://%s/repos/%s/%s/%s';
        $options = [
            $this->github_domain,
            $this->owner,
            $this->repository,
            $type,
        ];

        if ($this->pr_id) {
            $pattern = 'https://%s/repos/%s/%s/%s/%s/%s';
            $options[] = $this->pr_id;
            $options[] = $sub_type;
        }

        // Setup the URL
        $url = vsprintf($pattern, $options);

        // Strip trailing slash(es)
        $url = rtrim($url, '/');

        // Add querystring
        if ($method === 'GET') {
            $url .= !empty($data) ? '?' . http_build_query($data) : '';
        }

        $headers = ['Authorization: token ' . $this->oauth_token];

        if ($this->custom_headers) {
            foreach ($this->custom_headers as $header_key => $header_value) {
                $headers[] = sprintf('%s: %s', $header_key, $header_value);
            }
        }

        // Output debug info
        if ($this->debug && isset($data['body'], $data['position'], $data['path'])) {
            $body = str_replace("\n", ",", $data['body']);
            echo sprintf("GH Comment: %s on line %s in %s", $body, $data['position'], $data['path']) . PHP_EOL;
        }

        // Setup curl options
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Setup the data (note for POSTing, only JSON formatted bodies are supported with this method
        if ($method === 'POST' && is_array($data)) {
            $data_string = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data_string);
        }

        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $response_headers = [];

        // this function is called by curl for each header received
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$response_headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $name = strtolower(trim($header[0]));
                if (!array_key_exists($name, $response_headers)) {
                    $response_headers[$name] = [trim($header[1])];
                } else {
                    $response_headers[$name][] = trim($header[1]);
                }

                return $len;
            }
        );

        // Make request
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!$result) {
            throw new \Exception(sprintf(
                "Github Error: URL: %s\n\nRequest: %s\n\nStatus: %s",
                $url,
                json_encode($data, JSON_PRETTY_PRINT),
                $httpCode
            ), $httpCode);
        }

        $content_type = $response_headers['content-type'][0] ?? 'text/plain';

        if (strpos($content_type, 'application/json') !== false) {
            $response = json_decode($result);
        } else {
            // Not JSON
            $response = $result;
        }

        if (!isset($response)) {
            throw new \Exception(sprintf(
                "Github Error: URL: %s\n\nRequest: %s\n\nStatus: %s\n\nResponse: %s",
                $url,
                json_encode($data, JSON_PRETTY_PRINT),
                $httpCode,
                $result
            ), $httpCode);
        }

        // Paginate the response until there are no more entries
        if ($this->fetch_multiple_pages && $httpCode == 200 && $method === 'GET' && is_array($response) && count($response)) {
            if (!isset($data['page'])) {
                $data['page'] = 1;
            }
            $data['page']++;
            if ($data['page'] < 50) {
                $response = array_merge($response, $this->sendRequest($method, $type, $data, $sub_type));
            }
        }

        if ($httpCode >= 200 && $httpCode <= 299) {
            return $response;
        }

        throw new \Exception(sprintf(
            "Github Error: URL: %s\n\nRequest: %s\n\nStatus: %s\n\nResponse: %s",
            $url,
            json_encode($data, JSON_PRETTY_PRINT),
            $httpCode,
            json_encode($response, JSON_PRETTY_PRINT)
        ), $httpCode);
    }

    /**
     * Get PR info for a branch.
     * If PR number cannot be found, will return 0, '', and '' for the PR number, head and base shas respectively.
     * @param string $branch Branch name for which to look up the pull request
     * @return array hash of pr_number, head_sha, base_sha
     * @throws \Exception
     */
    public function getPullRequestInfo(string $branch): array
    {
        if (strpos($branch, 'origin/') === 0) {
            $branch = preg_replace('~^origin/~', '', $branch);
        }

        $result = $this->withoutMultiplePages()
            ->sendRequest('GET', 'pulls', ['sort' => 'updated', 'direction' => 'desc'], '');

        $pr_number = 0;
        $head_sha = '';
        $master_sha = '';
        $pull_request = null;
        /** @var \stdClass $pull_request */
        foreach ($result as $pull_request) {
            if ($pull_request->state === 'open' && $pull_request->head->ref === $branch) {
                $pr_number = $pull_request->number;
                break;
            }
        }
        if ($pr_number > 0) {
            $head_sha = $pull_request->head->sha;
            $master_sha = $pull_request->base->sha;
        }
        return [
            'pr_number' => $pr_number,
            'head_sha' => $head_sha,
            'base_sha' => $master_sha,
        ];
    }
}
