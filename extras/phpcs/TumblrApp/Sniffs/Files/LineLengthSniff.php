<?php

namespace TumblrApp\Sniffs\Files;

use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff as GenericLineLengthSniff;

/**
 * TumblrApp_Sniffs_Files_LineLengthSniff.
 * Extends Generic_Sniffs_Files_LineLengthSniff.
 *
 * Checks all lines in the file, and throws warnings if they are over 80
 * characters in length and errors if they are over 100. Both these
 * figures can be changed by extending this sniff in your own standard.
 *
 * Also checks if the line is within a test and in a data provider method.
 */
class LineLengthSniff extends GenericLineLengthSniff
{
    /**
     * The limit that the length of a line should not exceed.
     *
     * @var int
     */
    public $lineLimit = 170;

    /**
     * The limit that the length of a line must not exceed.
     *
     * Set to zero (0) to disable.
     *
     * @var int
     */
    public $absoluteLineLimit = 200;

    /**
     * The limit of a string line length
     *
     * @var int
     */
    public $stringLineLimit = 500;

    /**
     * Checks if a line is too long.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param array                $tokens    The token stack.
     * @param int                  $stackPtr  The first token on the next line.
     *
     * @return void|false
     */
    protected function checkLineLength($phpcsFile, $tokens, $stackPtr)
    {
        // The passed token is the first on the line.
        $stackPtr--;

        // Check if line has a _() or H() method on it, these lines are often long and contain translatable string
        if ($h = $this->findFirstOccurenceBefore($tokens, T_STRING, $stackPtr, array('_', 'h', '_c', '_cn'))) {
            if ($tokens[$h + 1]['code'] == T_OPEN_PARENTHESIS) {
                return;
            }
        }

        // Check if this is just a long string
        if ($string = $this->findFirstOccurenceBefore($tokens, T_CONSTANT_ENCAPSED_STRING, $stackPtr)) {
            $token = $tokens[$string];
            $length = $token['column'] + $token['length'];
            $min = min($this->lineLimit, $this->absoluteLineLimit);

            // String must have started < half the min width and greater than the min length to be ignored
            if ($token['column'] < $min / 2 && $length > $min) {
                if ($length > $this->stringLineLimit) {
                    $data = [
                        $this->stringLineLimit,
                        $length,
                    ];

                    $error = 'Line exceeds maximum string line limit of %s characters; contains %s characters';
                    $phpcsFile->addError($error, $stackPtr, 'MaxStringExceeded', $data);
                    return;
                }

                return;
            }
        }

        // Check if pointer exists within a function with "provider" in the name
        // Tests use data providers for test data, often these are long strings
        if (strpos($phpcsFile->getFilename(), '/tests/') !== false) {
            $function = $phpcsFile->findPrevious(T_FUNCTION, $stackPtr);
            if ($function &&
                isset($tokens[$function]['scope_opener']) &&
                isset($tokens[$function]['scope_closer']) &&
                $stackPtr > $tokens[$function]['scope_opener'] &&
                $stackPtr < $tokens[$function]['scope_closer']
            ) {

                $name = $phpcsFile->findNext(T_STRING, $function);
                if ($name && stripos($tokens[$name]['content'], 'provider') !== false) {
                    return;
                }
            }
        }

        // Increment stack pointer as the parent immediately decrements it
        parent::checkLineLength($phpcsFile, $tokens, $stackPtr+1);
    }//end checkLineLength()


    /**
     * Returns the position of the first occurance of a token on a line, matching given type.
     *
     * Returns false if no token can be found.
     *
     * @param array     $tokens  The array of tokens
     * @param int|array $types   The type(s) of tokens to search for.
     * @param int       $start   The position to start searching from in the
     *                           token stack. The first token matching on
     *                           this line before this token will be returned.
     * @param array     $values  The values that the token must be equal to.
     *                           If value is omitted, tokens with any value will
     *                           be returned.
     *
     * @return int | bool
     */
    protected function findFirstOccurenceBefore(array $tokens, $types, $start, array $values = array())
    {
        if (is_array($types) === false) {
            $types = array($types);
        }

        $foundToken = false;

        for ($i = $start; $i >= 0; $i--) {
            if ($tokens[$i]['line'] < $tokens[$start]['line']) {
                break;
            }

            // Check if type matches, and if value supplied, that matches too
            if (in_array($tokens[$i]['code'], $types) && (empty($values) || in_array($tokens[$i]['content'], $values))) {
                $foundToken = $i;
                break;
            }
        }//end for

        return $foundToken;

    }//end findFirstOnLine()

}//end class
