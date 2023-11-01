<?php

namespace TumblrExtras\phpcs;

use TumblrExtras\GitDiff\FileDiff;
use TumblrExtras\GitDiff\GitDiff;
use TumblrExtras\phpcs\Outputters\OutputterInterface;
use TumblrExtras\phpcs\Outputters\OutputterStdout;

/**
 * Parses a git diff against phpcs and cross references the resulting error line numbers with diff line numbers
 */
class PHPCSDiff
{
    /**
     * A map of error and warning `source` fields that should always be shown, even
     * when they are the result of changes outside added lines.
     *
     * @var array<string,bool>
     */
    private const ALWAYS_SHOW_CODES = [
        "TumblrApp.Files.LeadingWhitespace.LeadingWhitespace" => true,
    ];

    /**
     * @var GitDiff
     */
    protected $differ;

    /**
     * The path to PHPCBF
     *
     * @var
     */
    protected $phpcbf_path;

    /**
     * The PHPCS standard to use
     *
     * @var string
     */
    protected $phpcs_standard;

    /**
     * Allow auto fixing? Shows a propt to the user
     * @var bool
     */
    protected $allow_fix;

    /**
     * Source commit SHA
     * @var
     */
    protected $source_commit;

    /**
     * Target commit SHA
     * @var
     */
    protected $target_commit;

    /**
     * The branch being diffed
     * @var
     */
    protected $branch;

    /**
     * The diffed files, in the format:
     *
     * array(
     *   'filename' => array(
     *      + => array($file_line => $diff_line) // Additions
     *      - => array($file_line => $diff_line) // Subtractions
     *   )
     * )
     * @var FileDiff[]
     */
    protected $files = array();

    /**
     * The outputters to be used
     *
     * @var array
     */
    protected $outputters = array();

    /**
     * Success override
     *
     * @var bool
     */
    protected $success = true;

    /**
     * Indicates if user wants to override warnings
     *
     * @var bool
     */
    public $override_warnings = true;

    /**
     * Indicates if files contains errors
     *
     * @var bool
     */
    protected $files_have_errors = false;

    /**
     * Indicates if files contains warnings
     *
     * @var bool
     */
    protected $files_have_warnings = false;

    /** @var bool Non interactive mode (will not prompt to continue) */
    protected $non_interactive = false;

    /**
     * @param $phpcbf_path - Path the the PHPCS binary/phar/command
     * @param null $phpcs_standard - The PHPCS standard to use
     * @param bool|true $allow_fix - Offer to autofix the violatingfile
     * @param bool $non_interactive True for non-interactive mode, false for interactive mode
     */
    public function __construct($phpcbf_path, $phpcs_standard = null, $allow_fix = true, bool $non_interactive = false)
    {
        $this->phpcbf_path       = $phpcbf_path;
        $this->phpcs_standard    = $phpcs_standard;
        $this->allow_fix         = $allow_fix;
        $this->non_interactive   = $non_interactive;
    }

    /**
     * Adds an outputter to the outputter stack
     *
     * @param OutputterInterface $outputter
     */
    public function addOutputter(OutputterInterface $outputter)
    {
        $this->outputters[$outputter->get_handle()] = $outputter;
    }

    /**
     * Processes the file diffs
     *
     * @param FileDiff[] $files
     * @param null $source_commit
     * @param null $target_commit
     * @param null $branch
     * @return FileDiff[] The processed files
     */
    public function process(array $files, $source_commit = null, $target_commit = null, $branch = null)
    {
        $this->source_commit    = $source_commit;
        $this->target_commit    = $target_commit;
        $this->branch           = $branch;

        // Process the files through PHPCS
        $this->files = $this->processFiles($files);

        // Attempt to auto fix?
        if (count($this->files) && $this->allow_fix) {

            $this->runAutofix($this->files);

            $this->outputNewline('stdout', str_repeat('-', 80));
            $this->outputNewline('stdout', "Re-running phpcs to validate after fixing files");
            $this->outputNewline('stdout', str_repeat('-', 80));

            // Run files through phpcs again
            $this->files = $this->processFiles($this->files);
            $this->outputNewline('stdout', PHP_EOL);
        }

        // Output the log information
        $this->logOutput($this->files);

        return $this->files;
    }

    /**
     * Processes the files through PHPCS
     * Outputs E,W,. to signify progress
     *
     * @param FileDiff[] $files
     * @return array
     */
    protected function processFiles(array $files)
    {
        $todo       = array_keys($files);
        $num_files  = count($todo);

        $num_processed = 0;
        $dots         = 0;
        $maxLength    = strlen($num_files);
        $parsed       = array();

        foreach ($todo as $file) {
            $num_processed++;

            // Process the file, returns an object with info about the processing
            $file_diff = $this->processFile($files[$file]);

            // Show progress information.
            if (!$file_diff) {
                $this->output('stdout', '.');
                continue;
            }

            $errors = count($file_diff->errors);
            $warnings = count($file_diff->warnings);

            if ($errors > 0) {
                $this->output('stdout', '<red>E</red>');
            } else if ($warnings > 0) {
                $this->output('stdout', '<yellow>W</yellow>');
            } else {
                $this->output('stdout', '.');
            }

            $dots++;
            if ($dots === 60) {
                $dots = 0;
                $padding = ($maxLength - strlen($num_processed));
                $percent = round(($num_processed / $num_files) * 100);

                $this->output('stdout', str_repeat(' ', $padding));
                $this->outputNewline('stdout', " $num_processed / $num_files ($percent%)");
            }

            $parsed[$file] = $file_diff;
        }

        return $parsed;
    }

    /**
     * Process a file through PHPCS and compares the results with the lines changed from the diff
     *
     * @param FileDiff $file    The diff file
     * @return bool|FileDiff
     */
    protected function processFile(FileDiff $file)
    {
        $cmd = sprintf(
            "vendor/bin/phpcs --report=json -q %s",
            escapeshellarg($file->getFilename())
        );

        $phpcs_result = shell_exec($cmd);
        $data = json_decode($phpcs_result, true);

        // Show progress information.
        if (!$data) {
            $this->output('stdout', 'S');
            return false;
        }

        $added_lines = $file->getAddedLines();
        $errors = $this->filterMessages($this->getErrors($data), $added_lines);
        $warnings = $this->filterMessages($this->getWarnings($data), $added_lines);

        // If no errors or warnings left, bail
        if (empty($errors) && empty($warnings)) {
            return false;
        }

        // Ensure errors and warning are sorted by line
        ksort($errors);
        ksort($warnings);

        $file->errors = $errors;
        $file->warnings = $warnings;

        return $file;
    }

    /**
     * Filter messages that are not relevant to the diff and are not configured
     * to always show.
     *
     * @param array $messages The messages to filter
     * @param array $added_lines The diff'd added lines
     * @return array The messages excluding any that are filtered out
     */
    private function filterMessages(array $messages, array $added_lines): array
    {
        foreach (array_keys($messages) as $line) {
            // if the line was one of the addtions we want to keep it
            if (isset($added_lines[$line])) {
                continue;
            }

            // iterate the messages for the current line, and remove any that
            // are not defined in the always show map.
            foreach (array_keys($messages[$line]) as $index) {
                $source_field = $messages[$line][$index]["source"] ?? "";
                if (!isset(self::ALWAYS_SHOW_CODES[$source_field])) {
                    unset($messages[$line][$index]);
                }
            }

            // if there are no more messages for the line, we can just remove it
            if (empty($messages[$line])) {
                unset($messages[$line]);
            }
        }

        return $messages;
    }

    /**
     * Get errors only about a single file,
     * @param array $data Format:
     * [
     *     'totals' => hashmap,
     *     'files' => ['file1.php' => [hashmap], 'file2.php' => [] ... ]
     * ]
     * @return array Return format:
     * [
     *     10 => [
     *         ['message' => 'Ya dun goofed', 'type' => 'ERROR'],
     *         ['message' => 'Other thing...', 'type' => 'WARNING'],
     *     ],
     *     23 => [
     *         ['message' => 'Ya dun goofed', 'type' => 'ERROR'],
     *         ['message' => 'Other thing...', 'type' => 'WARNING'],
     *     ],
     * ]
     */
    protected function getErrors(array $data, string $type = 'ERROR'): array
    {
        $errors = [];
        if (!isset($data['files']) || !is_array($data['files']) || count($data['files']) < 1) {
            return [];
        }
        if (count($data['files']) > 1) {
            throw new \InvalidArgumentException(
                'getErrors only works with a single file, ' . count($data['files']) . ' given'
            );
        }
        $file_info = array_pop($data['files']);
        foreach ($file_info['messages'] as $message) {
            if ($message['type'] === $type) {
                $line = (int) $message['line'];
                if (!isset($errors[$line])) {
                    $errors[$line] = [];
                }
                $errors[$line][] = $message;
            }
        }
        return $errors;
    }

    /**
     * See getErrors for the format
     * @param array $data Input data
     * @return array
     */
    protected function getWarnings(array $data)
    {
        return $this->getErrors($data, 'WARNING');
    }

    /**
     * Output autofix command
     *
     * @param array $files - Array of files to be fixed
     * @return bool
     */
    protected function runAutofix(array $files)
    {
        $this->outputNewline(
            'stdout',
            "<yellow>Errors found in the following files:</yellow> \n\n%s\n\nAttempt to auto fix?[y/n]" . PHP_EOL,
            array(implode(PHP_EOL, array_keys($files)))
        );

        if (!$this->non_interactive) {
            // Output y/n prompt
            do {
                // Fetch response from stdin
                $response = strtolower(trim(fgets(STDIN)));

                // Fallback for git commit hook
                if (!$response) {
                    $response = $this->exec('exec < /dev/tty && read input && echo $input');
                    $response = trim(array_pop($response));
                }

                if (!in_array($response, array('y', 'n'))) {
                    $this->outputNewline('stdout', "You must enter 'y' or 'n'");
                }
            } while (!in_array($response, array('y', 'n')));
        } else {
            // Cannot prompt in non-interactive mode
            $response = 'n';
        }

        // If yes, fix and re-run sniffs
        if ($response === 'n') {
            return false;
        }

        // Fix the errors
        $this->fixPhpcsErrors($files);
        return true;
    }

    /**
     * Fix the errors using phpcbf
     *
     * @param array $files
     */
    protected function fixPhpcsErrors(array $files)
    {
        $this->outputNewline('stdout', PHP_EOL . str_repeat('-', 80));
        $this->outputNewline('stdout', "Attempting to fix files");
        $this->outputNewline('stdout', str_repeat('-', 80));

        $output = $this->exec(sprintf('%s --standard=%s %s', $this->phpcbf_path, $this->phpcs_standard, implode(' ', array_keys($files))));
        $this->output('stdout', implode(PHP_EOL, $output));

        $this->outputNewline('stdout');
    }

    /**
     * Executes a shell script
     *
     * @param $command - The command to run
     * @param null $status - The status code
     * @return array - The output of the command
     */
    protected function exec($command, &$status = null)
    {
        $output = array();
        exec($command, $output, $status);

        return $output;
    }

    /**
     * Prints the output from the PHPCS tool to the screen in the PHPCS format
     *
     * @param FileDiff[] $files
     */
    protected function logOutput(array $files)
    {
        $this->output('stdout', PHP_EOL);
        $fixable_problems = 0;

        foreach ($files as $i => $file) {

            if (!$file instanceof FileDiff) {
                throw new \InvalidArgumentException('Received something other than a FileDiff object at index ' . $i);
            }

            $filename = $file->getPathname();

            $errors = $file->errors;
            $warnings = $file->warnings;
            $lines = array_merge(array_keys($errors), array_keys($warnings));
            $lines = array_unique($lines);
            sort($lines);

            $this->outputNewline('stdout', PHP_EOL);
            $this->outputNewline('stdout', "FILE: %s", [$filename]);
            $this->outputNewline('stdout', str_repeat('-', 80));

            // Output the errors & line counts found
            $this->outputNewline('stdout', "<bold>FOUND %d ERRORS AFFECTING %d LINES</bold>", array(count($errors), count($lines)));
            $this->outputNewline('stdout', str_repeat('-', 80));

            // Work out the max line number length for formatting.
            $max_line_num_length = max(array_map('strlen', array_merge(array_keys($errors), array_keys($warnings))));

            // Output errors to screen
            foreach ($lines as $line_no) {

                $addition = $file->getAddedLine($line_no);
                $diff_line = $addition ? $addition->getDiffLine() : $line_no;
                $line_errors = isset($errors[$line_no]) ? $errors[$line_no] : array();
                $line_warnings = isset($warnings[$line_no]) ? $warnings[$line_no] : array();
                $has_warnings = !empty($line_warnings);

                $meta = array('file' => $filename, 'diff_line' => $diff_line, 'errors' => $line_errors, 'warnings' => $line_warnings);

                // Output errors
                foreach ($line_errors as $error) {

                    $message = $this->getErrorLine('ERROR', $error['message'], $max_line_num_length, $line_no, $error['fixable'], $has_warnings);
                    $this->outputNewline('stdout', $message, array(), $meta);

                    if ($error['fixable']) {
                        $fixable_problems++;
                    }

                    $this->files_have_errors = true;
                }

                // Output warnings
                foreach ($line_warnings as $warning) {

                    $message = $this->getErrorLine('WARNING', $warning['message'], $max_line_num_length, $line_no, $warning['fixable'], $has_warnings);
                    $this->outputNewline('stdout', $message, array(), $meta);

                    if ($warning['fixable']) {
                        $fixable_problems++;
                    }

                    $this->files_have_warnings = true;
                }

                // Send messages to github
                $this->output('github', null, [], $meta);
            }
        }
        if ($fixable_problems) {
            $this->outputNewline('stdout', PHP_EOL);
            $this->outputNewline('stdout', str_repeat('-', 80));
            $this->outputNewline('stdout', '<bold>PHPCBF CAN FIX THE %d MARKED SNIFF VIOLATIONS AUTOMATICALLY</bold>', [$fixable_problems]);
            $this->outputNewline('stdout', str_repeat('-', 80));
        }

        // Allow users to override all warnings at once if there are no errors
        if ($this->files_have_warnings && !$this->files_have_errors) {
            $msg = 'Your files contain warnings.';
            if (!$this->non_interactive) {
                $msg = 'Your files contain warnings. You may continue if you wish.';
            }

            $this->outputNewline('stdout', PHP_EOL);
            $this->outputNewline('stdout', sprintf('<yellow>%s</yellow>', $msg));
            $this->outputNewline('stdout', PHP_EOL);

            if (!$this->non_interactive) {
                $this->outputNewline(
                    'stdout',
                    'Do you wish to continue? [y/n]',
                    [implode(PHP_EOL, array_keys($files))]
                );

                // Output y/n prompt
                do {
                    // Fetch response from stdin
                    $response = strtolower(trim(fgets(STDIN)));

                    // Fallback for git commit hook
                    if (!$response) {
                        $response = $this->exec('exec < /dev/tty && read input && echo $input');
                        $response = trim(array_pop($response));
                    }

                    if (!in_array($response, ['y', 'Y', 'n', 'N'], true)) {
                        $this->outputNewline('stdout', "You must enter 'y' or 'n'");
                    }
                } while (!in_array($response, ['y', 'Y', 'n', 'N'], true));

                if (in_array($response, ['n', 'N'], true)) {
                    $this->override_warnings = false;
                }
                $this->output('stdout', PHP_EOL);
            }
        }
    }

    /**
     * Formats a line according to the PHPCS error format
     *
     * @param string $type              The error type, ERROR or WARNING
     * @param string $message           The error message
     * @param int $max_line_num_length  The maximum length of the line
     * @param int $line_no              The line number
     * @param bool $fixable             Fixable error?
     * @param bool $has_warnings        Are there warnings? If so, pad errors
     * @return string
     */
    protected function getErrorLine($type, $message, $max_line_num_length, $line_no, $fixable, $has_warnings)
    {
        $line_indent = $max_line_num_length - strlen($line_no);
        $color = $type === 'ERROR' ? 'red' : 'yellow';

        return sprintf(
            '%s%d | <%s>%s</%s>%s | [%s] %s',
            str_repeat(' ', $line_indent),
            $line_no,
            $color,
            $type,
            $color,
            $has_warnings && $type === 'ERROR' ? '  ' : '',
            $fixable ? 'x' : ' ',
            $message
        );
    }

    /**
     * Outputs a new line after message
     *
     * @param null $target
     * @param $message
     * @param array $vars
     * @param array $meta
     */
    protected function outputNewline($target = null, $message = null, array $vars = array(), array $meta = array())
    {
        return $this->output($target, $message . PHP_EOL, $vars, $meta);
    }

    /**
     * Outputs a line to the relevant outputter
     *
     * @param null $target
     * @param $message
     * @param array $vars
     * @param array $meta
     */
    protected function output($target = null, $message = null, array $vars = array(), array $meta = array())
    {
        $vars = $vars ?: array();

        // Perform sprintf replacements in message
        if (!empty($vars)) {
            array_unshift($vars, $message);
            $message = call_user_func_array('sprintf', $vars);
        }

        // Add additional values to meta
        $meta['source_commit'] = $this->source_commit;
        $meta['target_commit'] = $this->target_commit;
        $meta['branch'] = $this->branch;

        if (!$target) {
            foreach ($this->outputters as $outputter) {
                $outputter->write($message, $meta);
            }
            return;
        }

        if (!isset($this->outputters[$target])) {
            return;
        }

        try {
            $this->outputters[$target]->write($message, $meta);
        } catch (\Exception $e) {
            // Output error to screen
            if (!$this->outputters[$target] instanceof OutputterStdout) {
                $this->output('stdout', $e->getMessage() . PHP_EOL);
            }
            $this->success = false;
        }
    }

    /**
     * Run PHPCS on a file path (file or dir) and return a hash map of output and errors for each file path.
     * @param FileDiff[] $changed_files Hash of file path => FileDiff object
     * @param string[] $important_prefixes List of important dir string paths
     * @param string $basedir Root path of the tumblr dir, to strip out the path prefix
     * @return array
     */
    public function get_phpcs_errors(array $changed_files, array $important_prefixes, $basedir = ''): array
    {
        $phpcs = new \PHP_CodeSniffer\Runner();
        $orig_server_args = $_SERVER['argv'];
        $reports = [];
        foreach (array_keys($changed_files) as $file_path) {
            $important = false;
            $mod_path = ltrim(str_replace($basedir, '', $file_path), '/');
            foreach ($important_prefixes as $prefix) {
                if (strpos($mod_path, $prefix) === 0) {
                    $important = true;
                    break;
                }
            }
            if (!$important) {
                continue;
            }
            try {
                $_SERVER['argv'] = [
                    'vendor/bin/phpcs',
                    '-n', // turn off warnings
                    '-q', // quiet mode
                    '--no-colors',
                    \PHP_CodeSniffer\Util\Common::realpath($file_path),
                ];
                ob_start();
                $phpcs->runPHPCS();
                $report_output = ob_get_contents();
                $errors = (int) preg_match_all('~ ERROR ~', $report_output);

                if ($errors > 0) {
                    $reports[$file_path] = [
                        'errors' => $errors,
                        'output' => $report_output,
                    ];
                }
            } finally {
                ob_end_clean();
            }
        }
        $_SERVER['argv'] = $orig_server_args;

        return $reports;
    }
}
