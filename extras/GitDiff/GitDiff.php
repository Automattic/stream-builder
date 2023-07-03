<?php

namespace TumblrExtras\GitDiff;

use TumblrExtras\GitDiff\Differs\DifferInterface;

/**
 * Class GitDiff
 * Produces an index of filename and line numbers for files changed between 2 commits
 */
class GitDiff
{
    /** @var DifferInterface Interface that generates a string diff (CLI util or API call, or other) */
    protected $differ;

    public function __construct(DifferInterface $differ)
    {
        $this->differ = $differ;
    }

    /**
     * Perform the diff and return an array of FileDiff objects
     *
     * @param string $include An optional regex string to match on absolute file name to include
     * @param string $exclude An optional regex string to match on absolute file names to ignore
     * @return FileDiff[]
     */
    public function diff($include = null, $exclude = null): array
    {
        $diff = $this->differ->getDiffOutput();
        return $this->getFileDiffs($diff, $exclude, $include);
    }

    /**
     * Gets a list of files and lines numbers that have changed between 2 commits
     * The response is in the format:
     *
     * array(
     *   'filename' => array(
     *   array($line_from, $line_to)
     *   )
     * )
     *
     * @param string $diff_string Raw output of a `git diff` command or API call
     * @param string $exclude An optional grep ignore string
     * @param string $include An optional grep string
     * @return array
     */
    protected function getFileDiffs(string $diff_string, $exclude = null, $include = null)
    {
        $src_prefix = 'a/';
        $dst_prefix = 'b/';

        $diff_output = explode("\n", $diff_string);
        $files = array();
        $file = null;

        // Parse output to get the lines and files
        $diff_line = 0;
        $addition_line_no = -1;
        $remove_line_no = -1;
        foreach ($diff_output as $i => $line) {

            // Extract the file
            if (substr($line, 0, 3) === '---') {

                $nextline = $diff_output[$i + 1];

                // --- lines should be followed by +++
                if (substr($nextline, 0, 3) !== '+++') {
                    $file = null;
                    continue;
                }

                // Extract the filename from the next line
                $filename = substr($nextline, 4);

                // /dev/null is a deletion, the filename is listed on the current line
                $deleted = false;
                if ($filename === '/dev/null') {
                    $filename = substr($line, 4);
                    $deleted = true;
                }

                // Strip a/ and b/ or whatever else is used as prefix
                if (substr($filename, 0, strlen($dst_prefix)) === $dst_prefix) {
                    $filename = substr($filename, strlen($dst_prefix));
                } elseif (substr($filename, 0, strlen($src_prefix)) === $src_prefix) {
                    $filename = substr($filename, strlen($src_prefix));
                }

                // Check if file matches ignored
                if ($exclude && preg_match('#' . str_replace('#', '\#', $exclude) . '#', $filename)) {
                    $file = null;
                    continue;
                }

                // Check if file matches included
                if ($include && !preg_match('#' . str_replace('#', '\#', $include) . '#', $filename)) {
                    $file = null;
                    continue;
                }

                $file = new FileDiff($filename, $deleted);
                $files[$file->getRealPath()] = $file;
                $addition_line_no = null;
                $remove_line_no = null;
                $diff_line = -1;

                continue;
            }

            // Skip +++ lines, handled above
            if (substr($line, 0, 3) === '+++') {
                continue;
            }

            // If we haven't found a file, or it's deleted we can't continue yet
            if (!$file || $file->isDeleted()) {
                continue;
            }

            // Increment the diff line no
            $diff_line++;

            // Check for line marker
            if (substr($line, 0, 2) === '@@') {
                preg_match('#\+(\d+),?(\d*)#', $line, $match);

                $addition_line_no = (int) $match[1];
                $remove_line_no = (int) $match[1];
                continue;
            }

            // Ensure we have a line number
            if ($addition_line_no === null || $remove_line_no === null) {
                continue;
            }

            // Extract the lines first character
            $first_char = trim(substr($line, 0, 1));

            switch ($first_char) {
                case '+':

                    // If the line changed is the first line of a docblock (*), add the previous line is the opening of
                    // a docblock, then include the opening tag
                    if (
                        isset($diff_output[$i - 1]) &&
                        preg_match('#^\+\s+\*\s#', $line) &&
                        preg_match('#^\s+/\*\*#', $diff_output[$i - 1])
                    ) {
                        $file->addAddition(new LineDiff($addition_line_no - 1, $diff_line - 1, $diff_output[$i - 1]));
                    }

                    // If this line is the first line of a phpdoc (/**) and the next line is *not* changed, ignore this
                    // this happens when a function is added above another function that contains a docbloc due to the
                    // way the git diff works
                    if (
                        isset($diff_output[$i + 1]) &&
                        preg_match('#^\+\s*/\*\*#', $line) &&
                        preg_match('#^\s#', $diff_output[$i + 1])
                    ) {
                        break;
                    }

                    $file->addAddition(new LineDiff($addition_line_no, $diff_line, $line));
                    $addition_line_no++;
                    break;

                case '-':
                    $file->addSubtraction(new LineDiff($remove_line_no, $diff_line, $line));
                    $remove_line_no++;
                    break;

                case '':
                    $addition_line_no++;
                    $remove_line_no++;
                    break;
            }
        }

        return $files;
    }
}
