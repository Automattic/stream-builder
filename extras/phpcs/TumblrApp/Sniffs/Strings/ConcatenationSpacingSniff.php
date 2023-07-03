<?php

namespace TumblrApp\Sniffs\Strings;

/**
 * TumblrApp_Sniffs_Strings_ConcatenationSpacingSniff.
 *
 * Ensures concatenated strings have an operator surrounded by spaces
 *
 * 'foo' . 'bar';
 */
class ConcatenationSpacingSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_STRING_CONCAT);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token in the
     *                                        stack passed in $tokens.
     *
     * @return void
     */
    public function process(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $prefix_space = $tokens[($stackPtr - 1)]['code'] == T_WHITESPACE;
        $suffix_space = $tokens[($stackPtr + 1)]['code'] == T_WHITESPACE;

        if (!$prefix_space || !$suffix_space) {
            $message = 'Concat operator should be surrounded by spaces';
            $fix = $phpcsFile->addFixableError($message, $stackPtr, 'Missing');

            if (!$fix) {
                return;
            }

            if (!$prefix_space) {
                $phpcsFile->fixer->addContent(($stackPtr - 1), ' ');
            }

            if (!$suffix_space) {
                $phpcsFile->fixer->addContent($stackPtr, ' ');
            }
        }
    }
}
