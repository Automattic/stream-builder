<?php

namespace TumblrApp\Sniffs\CodeAnalysis;

use TumblrApp\Sniffs\Base\MethodArgumentSniffer;

/**
 * Looks for usages of in_array without the `true` third parameter
 */
class StrictInArraySniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    use MethodArgumentSniffer;

    /** @var string Error message to print when warning occurs */
    const ERROR_MESSAGE = 'If possible, please consider using the third parameter of ' .
        '[`in_array()`](http://yo/strictInArray) for type safety.';

    /** @var string Error name, for use in rule exclusions */
    const ERROR_NAME = 'NonStrictInArray';

    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return array(
            T_STRING,
        );
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        if (!isset($token['content']) || $token['content'] !== 'in_array') {
            // only listen for in_array statements
            return;
        }

        $args = $this->getMethodArguments(
            $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr),
            $tokens,
            $phpcsFile
        );

        // If the third argument is not set OR it is not 'true', warn
        if (!isset($args[2]) || strtolower($args[2]) !== 'true') {
            $phpcsFile->addWarning(
                static::ERROR_MESSAGE,
                $stackPtr,
                static::ERROR_NAME
            );
        }
    }
}
