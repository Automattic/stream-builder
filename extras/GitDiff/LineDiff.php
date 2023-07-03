<?php

namespace TumblrExtras\GitDiff;

/**
 * Class LineDiff
 *
 * Represents a single lines diff
 */
class LineDiff
{
    /**
     * The file line number
     *
     * @var int
     */
    private $line;

    /**
     * The diff line number
     *
     * @var int
     */
    private $diff_line;

    /**
     * The line content
     *
     * @var null|string
     */
    private $content = null;

    /**
     * @param int $line         The file line number
     * @param int $diff_line    The diff line number
     * @param null $content     The line content
     */
    public function __construct($line, $diff_line, $content = null)
    {
        $this->line = $line;
        $this->diff_line = $diff_line;
        $this->content = $content;
    }

    /**
     * Gets the file line number
     *
     * @return int
     */
    public function getLineNum()
    {
        return $this->line;
    }

    /**
     * Gets the diff line number
     *
     * @return int
     */
    public function getDiffLine()
    {
        return $this->diff_line;
    }

    /**
     * Gets the line content
     *
     * @return null|string
     */
    public function content()
    {
        return $this->content;
    }
}
