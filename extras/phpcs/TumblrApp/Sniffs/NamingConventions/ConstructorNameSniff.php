<?php

namespace TumblrApp\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\AbstractScopeSniff;

/**
 * TumblrApp_Sniffs_NamingConventions_ConstructorNameSniff.
 * Copy of Generic_Sniffs_NamingConventions_ConstructorNameSniff.
 *
 * Favor PHP 5 constructor syntax, which uses "function __construct()".
 * Avoid PHP 4 constructor syntax, which uses "function ClassName()".
 *
 * Static methods with the name of the class are incorrectly interpreted as constructors
 * Our ConstructorNameSniff fixes this issue.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @author   Leif Wickland <lwickland@rightnow.com>
 * @license  https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */
class ConstructorNameSniff extends AbstractScopeSniff
{
    /**
     * The name of the class we are currently checking.
     *
     * @var string
     */
    private $_currentClass = '';

    /**
     * A list of functions in the current class.
     *
     * @var string[]
     */
    private $_functionList = array();


    /**
     * Constructs the test with the tokens it wishes to listen for.
     */
    public function __construct()
    {
        parent::__construct(array(T_CLASS, T_INTERFACE), array(T_FUNCTION), true);

    }//end __construct()

    /**
     * @inheritDoc
     */
    protected function processTokenOutsideScope(File $phpcsFile, $stackPtr)
    {
    }

    /**
     * Processes this test when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     * @param int                  $currScope A pointer to the start of the scope.
     *
     * @return void
     */
    protected function processTokenWithinScope(
        \PHP_CodeSniffer\Files\File $phpcsFile,
        $stackPtr,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();
        $className = $phpcsFile->getDeclarationName($currScope);

        if ($className !== $this->_currentClass) {
            $this->loadFunctionNamesInScope($phpcsFile, $currScope);
            $this->_currentClass = $className;
        }

        $methodName = $phpcsFile->getDeclarationName($stackPtr);

        if (strcasecmp($methodName, $className) === 0) {

            // Check if method is static
            $static = $phpcsFile->findPrevious(T_STATIC, $stackPtr);

            if ((!$static || $tokens[$static]['line'] != $tokens[$stackPtr]['line']) && in_array('__construct', $this->_functionList) === false) {
                $error = 'PHP4 style constructors are not allowed; use "__construct()" instead';
                $phpcsFile->addError($error, $stackPtr, 'OldStyle');
            }
        }
    }//end processTokenWithinScope()

    /**
     * Extracts all the function names found in the given scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                  $currScope A pointer to the start of the scope.
     *
     * @return void
     */
    protected function loadFunctionNamesInScope(\PHP_CodeSniffer\Files\File $phpcsFile, $currScope)
    {
        $this->_functionList = array();
        $tokens = $phpcsFile->getTokens();

        for ($i = ($tokens[$currScope]['scope_opener'] + 1); $i < $tokens[$currScope]['scope_closer']; $i++) {
            if ($tokens[$i]['code'] !== T_FUNCTION) {
                continue;
            }

            $next = $phpcsFile->findNext(T_STRING, $i);
            $this->_functionList[] = trim($tokens[$next]['content']);
        }

    }//end loadFunctionNamesInScope()
}//end class
