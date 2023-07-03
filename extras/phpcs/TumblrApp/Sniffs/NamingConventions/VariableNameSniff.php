<?php

namespace TumblrApp\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs;
use PHP_CodeSniffer\Util\Tokens;

/**
 * VariableNameSniff detects camelCase variables and reports them.
 */
class VariableNameSniff extends Sniffs\AbstractVariableSniff
{
    private const FIX_VIOLATIONS = false;

    /** @var array Assignments tracker */
    protected $assignments = [];

    /**
     * Called to process class member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    protected function processMemberVar(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $var_name = ltrim($tokens[$stackPtr]['content'], '$');
        if ($this->isSnakeCase($var_name)) {
            return;
        }

        $member_props = $phpcsFile->getMemberProperties($stackPtr);
        if (empty($member_props)) {
            // Couldn't get any info about this variable, which
            // generally means it is invalid or possibly has a parse
            // error. Any errors will be reported by the core, so
            // we can ignore it.
            return;
        }

        $phpcsFile->addError(
            'Member variable "%s" is not in valid snake_case format',
            $stackPtr,
            'NotSnakeCase',
            [$var_name]
        );
    }


    /**
     * Called to process normal member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    protected function processVariable(File $phpcsFile, $stackPtr)
    {
        $tokens  = $phpcsFile->getTokens();
        $var_name = ltrim($tokens[$stackPtr]['content'], '$');

        // If it's a php reserved var, then its ok.
        if (isset($this->phpReservedVars[$var_name]) === true) {
            return;
        }

        if ($this->isSnakeCase($var_name) && $var_name !== 'this') {
            return;
        }

        // Check for variable assignments in function signatures, any format is allowed for overrides
        if (isset($tokens[$stackPtr]['nested_parenthesis'])) {
            $parens = $tokens[$stackPtr]['nested_parenthesis'];
            reset($parens);
            $start = key($parens);

            if (
                $start && // Ensure a start position was found
                isset($tokens[$start]['parenthesis_owner']) && // Check if open paren has a 'parenthesis_owner'
                isset($tokens[$tokens[$start]['parenthesis_owner']]['code']) && // Ensure 'parenthesis_owner' exists
                $tokens[$tokens[$start]['parenthesis_owner']]['code'] === T_FUNCTION // Ensure this is a function
            ) {
                return;
            }
        }

        // Skip self::$someVar
        $start = $phpcsFile->findStartOfStatement($stackPtr);
        if ($start && isset($tokens[$start]['type']) && $tokens[$start]['type'] === T_SELF) {
            return;
        }

        // Check if this is a variable assignment ($aBc = 123)
        $previous = $phpcsFile->findNext([T_WHITESPACE], $stackPtr + 1, null, true);
        $filename = $phpcsFile->getFilename();
        $is_assignment = false;
        $is_usage = false;
        $fixable = false;
        if ($previous && ($tokens[$previous]['code'] ?? '') === T_EQUAL) {
            $is_assignment = true;

            if (!isset($this->assignments[$filename])) {
                $this->assignments[$filename] = [];
            }

            if (!isset($this->assignments[$filename][$var_name])) {
                $this->assignments[$filename][$var_name] = [];
            }

            // Find the containing function pointer, this is used below when checking usages
            foreach ($tokens[$stackPtr]['conditions'] as $pointer => $code) {
                if ($code === T_FUNCTION) {
                    $this->assignments[$filename][$var_name][$pointer] = true;
                    $fixable = true;
                    break;
                }
            }
        } else {
            $is_usage = true;
        }

        // Check if this variable is a usage (not assignment) and has been assigned in the same scope (function)
        $has_assignment = false;
        if (
            $is_usage &&
            isset($this->assignments[$filename][$var_name]) &&
            isset($tokens[$stackPtr]['conditions'])
        ) {
            foreach ($tokens[$stackPtr]['conditions'] as $pointer => $code) {
                if ($code === T_FUNCTION && isset($this->assignments[$filename][$var_name][$pointer])) {
                    $has_assignment = true;
                    $fixable = true;
                    break;
                }
            }
        }

        // Don't warn about unfixable usages, e.g. using a function argument
        if (!($is_assignment || $has_assignment) && !$fixable) {
            return;
        }

        $fix = false;

        // Output a notice for fixable errors and assignments
        if ($fixable) {
            $fix = self::addFixableError(
                $phpcsFile,
                'Variable "%s" is not in valid snake_case format',
                $stackPtr,
                'NotSnakeCase',
                [$var_name]
            );
        } elseif ($is_assignment) {
            $phpcsFile->addError(
                'Variable "%s" is not in valid snake_case format',
                $stackPtr,
                'NotSnakeCase',
                [$var_name]
            );
        }

        if ($fix) {
            $phpcsFile->fixer->replaceToken($stackPtr, '$' . $this->camelToSnake($var_name));
        }
    }


    /**
     * Called to process variables found in double quoted strings or heredocs.
     *
     * Note that there may be more than one variable in the string, which will
     * result only in one call for the string or one call per line for heredocs.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the double quoted
     *                                               string was found.
     *
     * @return void|int Optionally returns a stack pointer. The sniff will not be
     *                  called again on the current file until the returned stack
     *                  pointer is reached. Return ($phpcsFile->numTokens + 1) to skip
     *                  the rest of the file.
     */
    protected function processVariableInString(File $phpcsFile, $stackPtr)
    {
        $filename = $phpcsFile->getFilename();
        $tokens  = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];
        if (preg_match_all('|[^\\\]\${?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|', $content, $matches) !== 0) {
            foreach ($matches[1] as $var_name) {
                // If it's a php reserved var, then its ok.
                if (isset($this->phpReservedVars[$var_name]) === true) {
                    continue;
                }

                if ($this->isSnakeCase($var_name)) {
                    continue;
                }

                $has_assignment = false;
                foreach ($tokens[$stackPtr]['conditions'] as $pointer => $code) {
                    if ($code === T_FUNCTION && isset($this->assignments[$filename][$var_name][$pointer])) {
                        $has_assignment = true;
                        break;
                    }
                }

                // If there was no assignment in scope, skip
                if (!$has_assignment) {
                    continue;
                }

                $fix = self::addFixableError(
                    $phpcsFile,
                    'Variable "%s" is not in valid snake_case format',
                    $stackPtr,
                    'NotSnakeCase',
                    [$var_name]
                );

                if ($fix) {
                    $camel_var = $this->camelToSnake($var_name);
                    $content = str_replace('$' . $var_name, '$' . $camel_var, $content);
                }
            }
        }

        if ($content !== $tokens[$stackPtr]['content']) {
            $phpcsFile->fixer->replaceToken($stackPtr, $content);
        }
    }

    /**
     * Convert camelCase string to snake_case
     *
     * @param string $var_name The variable name
     * @return string
     */
    protected function camelToSnake(string $var_name): string
    {
        // Split all lower=>upper & upper=>lower boundaries with _
        $callback = function ($matches) {
            return preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[1]);
        };

        return strtolower(preg_replace_callback('#([a-z][A-Z]|[A-Z][a-z])#', $callback, $var_name));
    }

    /**
     * Returns true if the string is snake case, that containing a capital letter followed by a lower case letter,
     * or a lower case letter followed by an uppercase letter
     *
     * @param string $var Input variable name
     * @return bool
     */
    private function isSnakeCase(string $var): bool
    {
        return preg_match('#[A-Z]+[a-z]#', $var) || preg_match('#[a-z]+[A-Z]#', $var) ? false : true;
    }

    /**
     * Optionally records a fixable error against a specific token in a file.
     *
     * Returns true if the error was recorded and should be fixed.
     *
     * @param File   $phpcsFile File object
     * @param string $error    The error message.
     * @param int    $stackPtr The stack position where the error occurred.
     * @param string $code     A violation code unique to the sniff message.
     * @param array  $data     Replacements for the error message.
     *
     * @return bool
     */
    private static function addFixableError(
        File $phpcsFile,
        $error,
        $stackPtr,
        $code,
        array $data = []
    ) {
        return $phpcsFile->addError(
            $error,
            $stackPtr,
            $code,
            $data,
            0,
            self::FIX_VIOLATIONS
        ) && self::FIX_VIOLATIONS;
    }
}
