<?php

namespace TumblrApp\Sniffs\ControlStructures;

/**
 * TumblrApp_Sniffs_ControlStructures_CaseDeclarationSniff.
 * Copy of PSR2_Sniffs_ControlStructures_SwitchDeclarationSniff.
 *
 * Ensures that case statements are followed by a newline
 */
class CaseDeclarationSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_SWITCH);
    }//end register()


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

        // We can't process SWITCH statements unless we know where they start and end.
        if (isset($tokens[$stackPtr]['scope_opener']) === false
            || isset($tokens[$stackPtr]['scope_closer']) === false
        ) {
            return;
        }

        $switch        = $tokens[$stackPtr];
        $nextCase      = $stackPtr;
        while (($nextCase = $this->_findNextCase($phpcsFile, ($nextCase + 1), $switch['scope_closer'])) !== false) {

            // Find the colon, end of the case statement
            if (!$colon = $phpcsFile->findNext(T_COLON, $nextCase+1)) {
                continue;
            }

            $nextToken = $phpcsFile->findNext(T_WHITESPACE, $colon+1, null, true);

            // If the next non-whitespace token is on the same line as the case statement, move it to a newline
            if ($tokens[$nextToken]['line'] == $tokens[$nextCase]['line']) {

                $error = "Case statements must be followed by a new line";

                $fix = $phpcsFile->addFixableError($error, $nextCase, 'CaseNoNewline');
                if ($fix === true) {
                    $phpcsFile->fixer->addNewlineBefore($nextToken);
                }
            }
        }//end while

    }//end process()


    /**
     * Find the next CASE or DEFAULT statement from a point in the file.
     *
     * Note that nested switches are ignored.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position to start looking at.
     * @param int                  $end       The position to stop looking at.
     *
     * @return int | bool
     */
    private function _findNextCase(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr, $end)
    {
        $tokens = $phpcsFile->getTokens();
        while (($stackPtr = $phpcsFile->findNext(array(T_CASE, T_DEFAULT, T_SWITCH), $stackPtr, $end)) !== false) {
            // Skip nested SWITCH statements; they are handled on their own.
            if ($tokens[$stackPtr]['code'] === T_SWITCH) {
                $stackPtr = $tokens[$stackPtr]['scope_closer'];
                continue;
            }

            break;
        }

        return $stackPtr;

    }//end _findNextCase()


}//end class
