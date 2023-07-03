<?php

namespace TumblrApp\Sniffs\ControlStructures;

/**
 * TumblrApp_Sniffs_ControlStructures_NewlineControlStructureSniff.
 *
 * Ensures the control structures have a newline proceeding the curl brace
 */
class NewlineControlStructureSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_IF, T_ELSE, T_ELSEIF, T_SWITCH, T_FOREACH, T_FOR, T_WHILE, T_TRY, T_CATCH, T_FINALLY);
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
        $brace = $phpcsFile->findNext(T_OPEN_CURLY_BRACKET, $stackPtr + 1);

        if ($brace && $next = $phpcsFile->findNext(array(T_WHITESPACE), $brace + 1, null, true)) {
            $prev = $phpcsFile->findPrevious(array(T_WHITESPACE), $brace - 1, null, true);
            $prev_token = $tokens[$prev];
            $next_token = $tokens[$next];

            // If we have a new line, continue
            if ($next_token['line'] !== $token['line']) {
                return;
            }

            if ($prev_token['content'] === '->') {
                return;
            }

            // If the next token is a closing PHP tag, and tag is an allowed inline control structure, ignore
            if (in_array($token['code'], [T_IF, T_ELSE, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE]) && $next_token['code'] === T_CLOSE_TAG && $next_token['line'] === $token['line']) {
                return;
            }

            $error  = 'Control structures must have a newline following the brace';
            $fix    = $phpcsFile->addFixableError($error, $stackPtr, 'MissingNewline');
            if ($fix === true) {
                $phpcsFile->fixer->addNewline($brace);
            }
        }
    }
}
