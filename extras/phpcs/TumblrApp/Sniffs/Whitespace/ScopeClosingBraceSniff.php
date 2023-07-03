<?php

namespace TumblrApp\Sniffs\Whitespace;

/**
 * Squiz_Sniffs_Whitespace_ScopeClosingBraceSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\ScopeClosingBraceSniff as SquizScopeClosingBraceSniff;

/**
 * Squiz_Sniffs_Whitespace_ScopeClosingBraceSniff.
 *
 * Checks that the closing braces of scopes are aligned correctly, ignores if a tpl file and closing brace
 * on the same line with an opening PHP tag preceeding the endif
 *
 */
class ScopeClosingBraceSniff extends SquizScopeClosingBraceSniff
{
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile All the tokens found in the document.
     * @param int                  $stackPtr  The position of the current token in the
     *                                        stack passed in $tokens.
     *
     * @return void
     */
    public function process(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // If this is an inline condition (ie. there is no scope opener), then
        // return, as this is not a new scope.
        if (isset($tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        // We need to actually find the first piece of content on this line,
        // as if this is a method with tokens before it (public, static etc)
        // or an if with an else before it, then we need to start the scope
        // checking from there, rather than the current token.
        $lineStart = $phpcsFile->findFirstOnLine(T_WHITESPACE, $stackPtr, true);

        $scopeStart  = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd    = $tokens[$stackPtr]['scope_closer'];

        // Check that the closing brace is on it's own line.
        $lastContent = $phpcsFile->findPrevious(array(T_INLINE_HTML, T_WHITESPACE, T_OPEN_TAG), ($scopeEnd - 1), $scopeStart, true);
        if ($tokens[$lastContent]['line'] !== $tokens[$scopeEnd]['line']) {
            return;
        }

        $prevOpenTag = $phpcsFile->findPrevious(array(T_OPEN_TAG), ($scopeEnd - 1), $scopeStart);

        if (!$prevOpenTag || $prevOpenTag < $scopeStart) {
            $error = 'Closing brace must be on a line by itself';
            $fix   = $phpcsFile->addFixableError($error, $scopeEnd, 'ContentBefore');
            if ($fix === true) {
                $phpcsFile->fixer->addNewlineBefore($scopeEnd);
            }
        }
    }//end process()
}//end class
