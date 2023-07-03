<?php

namespace TumblrApp\Sniffs\PHP;

use PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\ForbiddenFunctionsSniff as GenericForbiddenFunctionsSniff;

/**
 * Class which defines forbidden (deprecated) functions
 */
class ForbiddenFunctionsSniff extends GenericForbiddenFunctionsSniff
{
    /** @var bool Set to false so we produce warnings, not errors */
    public $error = false;

    /**
     * A list of forbidden functions with their alternatives.
     *
     * The value is NULL if no alternative exists. IE, the
     * function should just not be used.
     *
     * @var array(string => string|null)
     */
    public $forbiddenFunctions = [
        'sizeof'          => 'count',
        'delete'          => 'unset',
        'print'           => 'echo',
        'create_function' => 'an inline anonymous function',
    ];
}
