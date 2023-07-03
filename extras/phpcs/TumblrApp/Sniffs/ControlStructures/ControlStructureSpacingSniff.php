<?php

namespace TumblrApp\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\PSR2;

/**
 * Tumblr_Sniffs_WhiteSpace_ControlStructureSpacingSniff.
 * Copy of PSR2_Sniffs_WhiteSpace_ControlStructureSpacingSniff.
 *
 * Checks that control structures have the correct spacing around brackets.
 *
 * This is a custom implementation that allows for multi-line if statements, e.g.
 *
 * if (
 *     $a &&
 *     $b
 *     ) {
 *
 * }
 */
class ControlStructureSpacingSniff extends PSR2\Sniffs\ControlStructures\ControlStructureSpacingSniff
{
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $this->requiredSpacesAfterOpen   = (int) $this->requiredSpacesAfterOpen;
        $this->requiredSpacesBeforeClose = (int) $this->requiredSpacesBeforeClose;
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['parenthesis_opener']) === false
            || isset($tokens[$stackPtr]['parenthesis_closer']) === false
        ) {
            return;
        }

        $parenOpener    = $tokens[$stackPtr]['parenthesis_opener'];
        $parenCloser    = $tokens[$stackPtr]['parenthesis_closer'];
        $spaceAfterOpen = 0;
        if ($tokens[($parenOpener + 1)]['code'] === T_WHITESPACE) {
            if (strpos($tokens[($parenOpener + 1)]['content'], $phpcsFile->eolChar) !== false) {
                $spaceAfterOpen = $this->requiredSpacesAfterOpen;
            } else {
                $spaceAfterOpen = strlen($tokens[($parenOpener + 1)]['content']);
            }
        }

        $phpcsFile->recordMetric($stackPtr, 'Spaces after control structure open parenthesis', $spaceAfterOpen);

        if ($spaceAfterOpen !== $this->requiredSpacesAfterOpen) {
            $error = 'Expected %s spaces after opening bracket; %s found';
            $data  = array(
                      $this->requiredSpacesAfterOpen,
                      $spaceAfterOpen,
                     );
            $fix   = $phpcsFile->addFixableError($error, ($parenOpener + 1), 'SpacingAfterOpenBrace', $data);
            if ($fix === true) {
                $padding = str_repeat(' ', $this->requiredSpacesAfterOpen);
                if ($spaceAfterOpen === 0) {
                    $phpcsFile->fixer->addContent($parenOpener, $padding);
                } else if ($spaceAfterOpen === 'newline') {
                    $phpcsFile->fixer->replaceToken(($parenOpener + 1), '');
                } else {
                    $phpcsFile->fixer->replaceToken(($parenOpener + 1), $padding);
                }
            }
        }

        if ($tokens[$parenOpener]['line'] === $tokens[$parenCloser]['line']) {
            $spaceBeforeClose = 0;
            if ($tokens[($parenCloser - 1)]['code'] === T_WHITESPACE) {
                $spaceBeforeClose = strlen(ltrim($tokens[($parenCloser - 1)]['content'], $phpcsFile->eolChar));
            }

            $phpcsFile->recordMetric($stackPtr, 'Spaces before control structure close parenthesis', $spaceBeforeClose);

            if ($spaceBeforeClose !== $this->requiredSpacesBeforeClose) {
                $error = 'Expected %s spaces before closing bracket; %s found';
                $data  = array(
                          $this->requiredSpacesBeforeClose,
                          $spaceBeforeClose,
                         );
                $fix   = $phpcsFile->addFixableError($error, ($parenCloser - 1), 'SpaceBeforeCloseBrace', $data);
                if ($fix === true) {
                    $padding = str_repeat(' ', $this->requiredSpacesBeforeClose);
                    if ($spaceBeforeClose === 0) {
                        $phpcsFile->fixer->addContentBefore($parenCloser, $padding);
                    } else {
                        $phpcsFile->fixer->replaceToken(($parenCloser - 1), $padding);
                    }
                }
            }
        }//end if

        // Check for boolean not operator followed by space
        for ($i = $parenOpener + 1; $i < $parenCloser; $i++) {

            if ($tokens[$i]['code'] === T_BOOLEAN_NOT && isset($tokens[$i + 1]) && $tokens[$i + 1]['code'] === T_WHITESPACE) {

                $error = 'Expected no space after boolean operator, space(s) found';
                $fix   = $phpcsFile->addFixableError($error, $i, 'SpaceAfterBooleanNot');

                if ($fix === true) {
                    $phpcsFile->fixer->replaceToken($i + 1, '');
                }
            }
        }

    }//end process()


}//end class
