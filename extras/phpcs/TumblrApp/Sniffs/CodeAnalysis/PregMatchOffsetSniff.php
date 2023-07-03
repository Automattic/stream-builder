<?php

namespace TumblrApp\Sniffs\CodeAnalysis;

use TumblrApp\Sniffs\Base\MethodArgumentSniffer;

/**
 * Looks for usages of preg_match or preg_match_all with the offset argument. This is fine, but we want to warn
 * when using offset along with the 'u' flag (UTF-8) since in PHP 7.4 there is an odd edge case.
 */
class PregMatchOffsetSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    use MethodArgumentSniffer;

    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [
            T_STRING,
        ];
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

        if (!isset($token['content']) || strpos($token['content'], 'preg_match') !== 0) {
            // only listen for preg_match* statements
            return;
        }

        $args = $this->getMethodArguments(
            $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr),
            $tokens,
            $phpcsFile
        );

        // If the 5th argument isn't there ($offset), ignore
        if (!isset($args[4])) {
            return;
        }

        // Warn about the weird UTF-8 edge case since it's using $offset
        $phpcsFile->addWarning(
            sprintf('Note: Be careful when using $offset with %s and the \'u\' flag. See https://bit.ly/2NANcD9', $token['content']),
            $stackPtr,
            'PregMatchOffsetUtf8Warn'
        );
    }
}
