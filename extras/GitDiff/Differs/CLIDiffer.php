<?php

namespace TumblrExtras\GitDiff\Differs;

/**
 * Class CLIDiffer
 * Uses exec with the git command-line utility to get diff information
 */
class CLIDiffer implements DifferInterface
{
    /** @var string Source Git SHA */
    protected $source_sha;

    /** @var string Target Git SHA */
    protected $target_sha;

    /** @var bool Staged or not */
    protected $staged;

    public function __construct($source_sha, $target_sha, $staged)
    {
        $this->validateInputs($source_sha, $target_sha, $staged);
        $this->source_sha = $source_sha;
        $this->target_sha = $target_sha;
        $this->staged = $staged;
    }

    /**
     * Validate the input parameters
     *
     * @param string $source_sha The source sha to diff from
     * @param string $target_sha The target sha to diff to
     * @param bool $staged If true, use the staged diff
     * @return void
     */
    protected function validateInputs($source_sha = null, $target_sha = null, $staged = false)
    {
        // Ensure staged OR commits
        if ($staged && ($source_sha || $target_sha)) {
            throw new \InvalidArgumentException('You cannot specify "staged" with "source" OR "target"');
        }

        if (!$source_sha && $target_sha) {
            throw new \InvalidArgumentException('You must specify a source "source" when specifying a "target"');
        }
    }

    /**
     * Executes a shell script
     *
     * @param string $command The command to run
     * @return array The output of the command (array of lines)
     *
     * @throws \RuntimeException If/when the status code is > 0 (failure occurred)
     */
    protected function exec(string $command)
    {
        $output = [];
        $status = null;
        exec($command, $output, $status);

        if (is_int($status) && $status > 0) {
            throw new \RuntimeException(
                sprintf('Failed to exec command %s, got status %d', $command, $status)
            );
        }
        return $output;
    }

    /**
     * @inheritDoc
     */
    public function getDiffOutput(): string
    {
        $src_prefix = 'a/';
        $dst_prefix = 'b/';

        $cmd = sprintf(
            'git diff -M --no-color --src-prefix=%s --dst-prefix=%s %s %s%s',
            $src_prefix,
            $dst_prefix,
            ($this->staged ? '--staged ' : ''),
            (!$this->staged && $this->source_sha ? (escapeshellarg($this->source_sha) . '...') : ''),
            (!$this->staged && $this->source_sha && $this->target_sha ? escapeshellarg($this->target_sha) : '')
        );

        // Get the diff between the source and destination commits
        $diff_output = $this->exec($cmd);

        return implode("\n", $diff_output);
    }
}
