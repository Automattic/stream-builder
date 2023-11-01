<?php

namespace TumblrApp\Sniffs\Formatting;

/**
 * TumblrApp_Sniffs_Formatting_CurlyNewlineSniff.
 *
 * Curly newline sniff
 *
 * Checks that the class/function opening brace is not followed by an empty newline
 */
class CurlyNewlineSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(
            T_CLASS,
            T_INTERFACE,
            T_TRAIT,
            T_FUNCTION,
        );
    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param integer              $stackPtr  The position of the current token in the
     *                                        stack passed in $tokens.
     *
     * @return void
     */
    public function process(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        $tokens    = $phpcsFile->getTokens();

        // Interfaces are not followed by a curly brace
        if (false === $curly = $phpcsFile->findNext(T_OPEN_CURLY_BRACKET, $stackPtr+1)){
            return;
        }

        // Prevent double error when curly is on same line as class, PSR2 will error about this anyway
        if ($tokens[$curly]['line'] == $tokens[$stackPtr]['line']) {
            return;
        }

        // Ensure we don't have an empty class/method
        $has_body = false;
		$token_count = is_countable($tokens) ? count($tokens) : 0;
        for ($i = $curly + 1; $i < $token_count; $i++) {

            // If we make it to the closing curly, stop
            if ($i >= $tokens[$curly]['scope_closer']) {
                break;
            }

            $token = $tokens[$i];
            if (!in_array($token['code'], array(
                T_WHITESPACE,
                T_CLOSE_CURLY_BRACKET,
                T_COMMENT,
                T_DOC_COMMENT_STAR,
                T_DOC_COMMENT_WHITESPACE,
                T_DOC_COMMENT_TAG,
                T_DOC_COMMENT_OPEN_TAG,
                T_DOC_COMMENT_CLOSE_TAG,
                T_DOC_COMMENT_STRING
            ))) {
                $has_body = true;
                break;
            }
        }

        if (!$has_body) {
            return;
        }

        // Find the next non-whitespace token
        $nextToken = $phpcsFile->findNext(T_WHITESPACE, $curly+1, null, true);

        // If next token is not on the line after curly, error
        if ($tokens[$nextToken]['line'] != $tokens[$curly]['line']+1) {

            $error = "Curly brace must not be followed by blank newlines";

            $fix = $phpcsFile->addFixableError($error, $nextToken, 'CurlyoNewline');
            if ($fix === true) {
                for ($i = $curly+1; $i < $nextToken; $i++) {
                    $phpcsFile->fixer->replaceToken($i, null);
                }
            }
        }
    }//end process()
}//end class
