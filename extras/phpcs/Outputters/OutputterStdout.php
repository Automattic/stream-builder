<?php

namespace TumblrExtras\phpcs\Outputters;

/**
 * Class OutputterStdout
 *
 * The standard out outputter implementation, writes to screen
 *
 */
class OutputterStdout implements OutputterInterface
{
    /**
     * Enable colors
     *
     * @var bool
     */
    public $colors = true;

    /**
     * Replacement characters, used to syntax highlight the output
     * @var array
     */
    protected $replacements = array('red' => '[31m', 'green' => '[23m', 'yellow' => '[33m', '[34m' => 'blue', '[35m' => 'purple', '[36m' => 'teal', 'bold' => '[1m');

    /**
     * Gets the handle for the outputter
     *
     * @return string
     */
    public function get_handle()
    {
        return 'stdout';
    }

    /**
     * Outputs a line to screen
     *
     * @param $message
     * @param array $meta
     */
    public function write($message, array $meta = array(), bool $buffer = true)
    {
        echo $this->parse_terminal_output($message);
    }

    /**
     * Parses a terminal output and replaces <placeholder> with the correct code
     *
     * @param $string
     * @return mixed
     */
    protected function parse_terminal_output($string)
    {
        $endchar = '[0m';

        foreach ($this->replacements as $key => $value) {
            $string = str_replace('<' . $key . '>', $this->colors ? chr(27) . $value : '', $string);
            $string = str_replace('</' . $key . '>', $this->colors ? chr(27) . $endchar : '', $string);
        }

        return $string;
    }
}
