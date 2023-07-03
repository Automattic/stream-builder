<?php

namespace TumblrExtras\GitDiff;

/**
 * Class FileDiff
 *
 * Represents
 */
class FileDiff extends \SplFileInfo
{
    /**
     * The file name
     *
     * @var string
     */
    private $filename;

    /**
     * The lines that have had additions
     *
     * @var array
     */
    private $added_lines = array();

    /**
     * The lines that have had subtractions
     *
     * @var array
     */
    private $subtracted_lines = array();

    /**
     * @var bool Was the file deleted
     */
    private $deleted = false;

    /** @var array Array of PHPCS error violations */
    public $errors;

    /** @var array Array of PHPCS warning violations */
    public $warnings;

    /**
     * @param string $filename The filename
     * @param bool $deleted If the file was deleted
     */
    public function __construct($filename, $deleted = false)
    {
        parent::__construct($filename);
        $this->filename = $filename;
        $this->deleted = $deleted;
    }

    /**
     * Add a line had an addition
     *
     * @param LineDiff $line
     */
    public function addAddition(LineDiff $line)
    {
        $this->added_lines[$line->getLineNum()] = $line;
    }

    /**
     * Add a line that had a subtraction
     *
     * @param LineDiff $line
     */
    public function addSubtraction(LineDiff $line)
    {
        $this->subtracted_lines[$line->getLineNum()] = $line;
    }

    /**
     * Returns the filename
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Returns all the added lines
     *
     * @return array
     */
    public function getAddedLines()
    {
        ksort($this->added_lines);
        return $this->added_lines;
    }

    /**
     * Returns the line diff for an added line if it exists
     *
     * @param int $line - The line number
     * @return null|LineDiff
     */
    public function getAddedLine($line)
    {
        return $this->hasAddedLine($line) ? $this->added_lines[$line] : null;
    }

    /**
     * Returns true if the file has a line added/modified at the supplied line number
     *
     * @param int $line The line number to check
     * @return bool
     */
    public function hasAddedLine($line)
    {
        return isset($this->added_lines[$line]);
    }

    /**
     * Return all the subtracted lines
     *
     * @return array
     */
    public function getSubtractedLines()
    {
        ksort($this->subtracted_lines);
        return $this->subtracted_lines;
    }

    /**
     * Returns the line diff for an added line if it exists
     *
     * @param int $line - The line number
     * @return null|LineDiff
     */
    public function getSubtractedLine($line)
    {
        return $this->hasSubtractedLine($line) ? $this->subtracted_lines[$line] : null;
    }

    /**
     * Returns true if the file has a line subtracted/removed at the supplied line number
     *
     * @param int $line The line number to check
     * @return bool
     */
    public function hasSubtractedLine($line)
    {
        return isset($this->subtracted_lines[$line]);
    }

    /**
     * @return bool Returns true if file is deleted
     */
    public function isDeleted()
    {
        return $this->deleted;
    }
}
