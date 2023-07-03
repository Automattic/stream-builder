<?php

namespace TumblrApp\Sniffs\Commenting;

/**
 * TumblrApp_Sniffs_Commenting_InlineCommentSniff.
 *
 * Checks inline comments a prefixed with a space
 */
class InlineCommentSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_COMMENT);
    } // end register()

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

        // If not a comment, skip
        if (substr($tokens[$stackPtr]['content'], 0, 2) != '//') {
            return;
        }

        // If comment starts correctly, skip
        if (substr($tokens[$stackPtr]['content'], 0, 3) == '// ') {
            return;
        }

        // If comment is the end of the line, skip
        if (strlen(trim($tokens[$stackPtr]['content'])) == 2 && isset($tokens[$stackPtr + 1]) && $tokens[$stackPtr + 1]['line'] != $tokens[$stackPtr]['line']) {
            return;
        }

        $error = 'Inline comments must be prefixed with a space';
        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'MissingSpace');
        if ($fix === true) {
            $newComment = substr($tokens[$stackPtr]['content'], 0, 2) . ' ' . substr($tokens[$stackPtr]['content'], 2);
            $phpcsFile->fixer->replaceToken($stackPtr, $newComment);
        }
    }
}
