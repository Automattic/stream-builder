<?php

namespace TumblrApp\Sniffs\NamingConventions;

use PHP_CodeSniffer\Sniffs\AbstractScopeSniff;

/**
 * TumblrApp_Sniffs_NamingConventions_FunctionDoubleUnderscoreSniff.
 *
 * Detects if double underscore functions are used that are not core PHP double underscore functions
 */
class FunctionDoubleUnderscoreSniff extends AbstractScopeSniff
{
    /**
     * A list of all PHP magic methods.
     *
     * @var array
     */
    protected $magicMethods = array(
        'construct',
        'destruct',
        'call',
        'callstatic',
        'get',
        'set',
        'isset',
        'unset',
        'sleep',
        'wakeup',
        'tostring',
        'set_state',
        'clone',
        'invoke',
        'call',
    );

    /**
     * A list of all PHP non-magic methods starting with a double underscore.
     *
     * These come from PHP modules such as SOAPClient.
     *
     * @var array
     */
    protected $methodsDoubleUnderscore = array(
        'soapcall',
        'getlastrequest',
        'getlastresponse',
        'getlastrequestheaders',
        'getlastresponseheaders',
        'getfunctions',
        'gettypes',
        'dorequest',
        'setcookie',
        'setlocation',
        'setsoapheaders',
    );

    /**
     * A list of all PHP magic functions.
     *
     * @var array
     */
    protected $magicFunctions = array('autoload');

    /**
     * If TRUE, the string must not have two capital letters next to each other.
     *
     * @var bool
     */
    public $strict = true;


    /**
     * Constructs a Generic_Sniffs_NamingConventions_CamelCapsFunctionNameSniff.
     */
    public function __construct()
    {
        parent::__construct(array(T_CLASS, T_INTERFACE, T_TRAIT), array(T_FUNCTION), true);

    }

    // end __construct()


    /**
     * Processes the tokens within the scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     * @param int                  $currScope The position of the current scope.
     *
     * @return void
     */
    protected function processTokenWithinScope(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr, $currScope)
    {
        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null) {
            // Ignore closures.
            return;
        }

        $className = $phpcsFile->getDeclarationName($currScope);
        $errorData = array($className . '::' . $methodName);

        // Is this a magic method. i.e., is prefixed with "__" ?
        if (preg_match('|^__|', $methodName) !== 0) {
            $magicPart = strtolower(substr($methodName, 2));
            if (in_array($magicPart, array_merge($this->magicMethods, $this->methodsDoubleUnderscore)) === false) {
                $error = 'Method name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';
                $phpcsFile->addWarning($error, $stackPtr, 'MethodDoubleUnderscore', $errorData);
            }

            return;
        }

        // PHP4 constructors are allowed to break our rules.
        if ($methodName === $className) {
            return;
        }

        // PHP4 destructors are allowed to break our rules.
        if ($methodName === '_' . $className) {
            return;
        }
    }

    /**
     * Processes the tokens outside the scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     *
     * @return void
     */
    protected function processTokenOutsideScope(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        $functionName = $phpcsFile->getDeclarationName($stackPtr);
        if ($functionName === null) {
            // Ignore closures.
            return;
        }

        $errorData = array($functionName);

        // Is this a magic function. i.e., it is prefixed with "__".
        if (preg_match('|^__|', $functionName) !== 0) {
            $magicPart = strtolower(substr($functionName, 2));
            if (in_array($magicPart, $this->magicFunctions) === false) {
                $error = 'Function name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';
                $phpcsFile->addWarning($error, $stackPtr, 'FunctionDoubleUnderscore', $errorData);
            }

            return;
        }
    }
}
