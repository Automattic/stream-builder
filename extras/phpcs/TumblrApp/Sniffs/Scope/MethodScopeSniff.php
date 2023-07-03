<?php

namespace TumblrApp\Sniffs\Scope;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\AbstractScopeSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * TumblrApp_Sniffs_Scope_MethodScopeSniff.
 * Copy of Squiz_Sniffs_Scope_MethodScopeSniff.
 *
 * Verifies that class methods have scope modifiers.
 *
 * This is a copy of the base Squiz_Sniffs_Scope_MethodScopeSniff which fails to account for
 * named functions defined within the scope of another function
 */
class MethodScopeSniff extends AbstractScopeSniff
{
    /**
     * Constructs a Squiz_Sniffs_Scope_MethodScopeSniff.
     */
    public function __construct()
    {
        parent::__construct(array(T_CLASS, T_INTERFACE), array(T_FUNCTION));

    }//end __construct()

    /**
     * @inheritDoc
     */
    protected function processTokenOutsideScope(File $phpcsFile, $stackPtr)
    {
    }

    /**
     * Processes the function tokens within the class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where this token was found.
     * @param int                  $stackPtr  The position where the token was found.
     * @param int                  $currScope The current scope opener token.
     *
     * @return void
     */
    protected function processTokenWithinScope(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr, $currScope)
    {
        $tokens = $phpcsFile->getTokens();

        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null) {
            // Ignore closures.
            return;
        }

        $modifier = null;
        for ($i = ($stackPtr - 1); $i > 0; $i--) {
            if ($tokens[$i]['line'] < $tokens[$stackPtr]['line']) {
                break;
            }
            if (isset(Tokens::$scopeModifiers[$tokens[$i]['code']]) === true) {
                $modifier = $i;
                break;
            }
        }

        if ($modifier) {
            return;
        }

        // Look for parent function
        $parent_scope = $phpcsFile->findPrevious(array(T_FUNCTION), $stackPtr - 1);

        $opener = isset($tokens[$parent_scope]['scope_opener']) ? $tokens[$parent_scope]['scope_opener'] : null;
        $closer = isset($tokens[$parent_scope]['scope_closer']) ? $tokens[$parent_scope]['scope_closer'] : null;

        $method_abstract = '';

        // Check if function is defined within another functions scope
        if ($opener === null || $closer === null) {
            $method_abstract = 'abstract ';
        } elseif ($parent_scope && $stackPtr > $opener && $stackPtr < $closer) {
            return;
        }

        $error = 'Visibility must be declared on %smethod "%s"';
        $data  = array(
            $method_abstract,
            $methodName,
        );
        $phpcsFile->addError($error, $stackPtr, 'Missing', $data);

    }//end processTokenWithinScope()
}//end class
