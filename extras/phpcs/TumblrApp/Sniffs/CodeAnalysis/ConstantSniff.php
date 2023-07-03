<?php

namespace TumblrApp\Sniffs\CodeAnalysis;

/**
 * ConstantSniff.
 *
 * Ensures certain constants are used instead of their literal value
 */
class ConstantSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Constants to check, keys as constant name, value as constant value. May need to update register() for other types
     */
    private const CONSTANTS = [
        // @phpcs:disable TumblrApp.CodeAnalysis.Constant
        'QUERY_SORT_ASC' => 'asc',
        'QUERY_SORT_DESC' => 'desc',
        'SECONDS_PER_HOUR' => 3600,
        'SECONDS_PER_DAY' => 86400,
        'SECONDS_PER_MINUTE' => 60,
        // @phpcs:enable
    ];

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_STRING];
    }

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
        $token = $tokens[$stackPtr];

        // Check if we've got a value that should be a constant.
        foreach (static::CONSTANTS as $constant => $value) {
            $result = $this->checkConstant($constant, $value, $phpcsFile, $stackPtr);

            // Special case for sort dir, value can be uppercase too
            if (!$result && ($constant === 'QUERY_SORT_ASC' || $constant === 'QUERY_SORT_DESC')) {
                $this->checkConstant($constant, strtoupper($value), $phpcsFile, $stackPtr);
            }
        }
    }

    /**
     * Check a specific constant usage
     *
     * @param string $constant The constant name
     * @param mixed $constant_value The value to check
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr Current stack pointer
     * @return bool
     */
    private function checkConstant(string $constant, $constant_value, \PHP_CodeSniffer\Files\File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        // If the constant is a string, trim the quotes.
        // This also ensures that string versions of number constants are not matched.
        if (is_string(static::CONSTANTS[$constant])) {
            $content = trim($token['content'], '"\'');
        } else {
            $content = $token['content'];
        }

        $prev = $phpcsFile->findPrevious(T_STRING, $stackPtr);
        $prev_token = $prev !== false ? $tokens[$prev] : null;

        // If value is not the constant, skip.
        if ($content != $constant_value) {
            return false;
        }

        // If prev token is the constant definition, skip
        if ($prev_token && $prev_token['content'] === $constant) {
            return false;
        }

        $fix = $phpcsFile->addFixableWarning(sprintf('Please use the constant %s', $constant), $stackPtr, $constant);
        if ($fix) {
            $phpcsFile->fixer->replaceToken($stackPtr, $constant);
        }

        return true;
    }
}
