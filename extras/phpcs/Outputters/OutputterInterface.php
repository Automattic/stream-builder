<?php

namespace TumblrExtras\phpcs\Outputters;

/**
 * Interface OutputterInterface
 *
 * Defines the interface that all PHPCS_Diff outputters must conform to
 *
 */
interface OutputterInterface
{
    /**
     * Gets the handle for the outputter
     *
     * @return string
     */
    public function get_handle();

    /**
     * Outputs a line using the formatters output mechanism
     *
     * @param string $message The message to write
     * @param array $meta Meta values used when writing the message
     * @param bool $buffer If true, the message is buffered until later
     * @return void
     */
    public function write($message, array $meta = array(), bool $buffer = true);
}
