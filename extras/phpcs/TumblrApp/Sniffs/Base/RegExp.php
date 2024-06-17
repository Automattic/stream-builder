<?php

namespace TumblrApp\Sniffs\Base\RegExp;

/**
 * Base sniff for regexp-based checks
 */
abstract class RegExp implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /** @var array List of regular expressions to look for. Override this in the base class */
    const REG_EXPRESSIONS = [];

    /** @var string Error message to print when warning occurs */
    const ERROR_MESSAGE = '';

    /** @var string Error name, for use in rule exclusions */
    const ERROR_NAME = '';

    /** @var bool If true, only the line that the token match is found on will be matched against */
    const SINGLE_LINE = false;

    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [];
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
        if ($this->isTokenIgnored($phpcsFile, $stackPtr)) {
            return;
        }

        $start = $phpcsFile->findStartOfStatement($stackPtr);

        if (static::SINGLE_LINE) {
            $tokens = $phpcsFile->getTokens();
            $line = $tokens[$start]['line'];
            $length = is_countable($tokens) ? count($tokens) : 0;
            $token_length = 1;
            for ($i = $stackPtr + 1; $i < $length; $i++) {
                if ($tokens[$i]['line'] > $line) {
                    break;
                }
                $token_length++;
            }
        } else {
            $end = $phpcsFile->findEndOfStatement($stackPtr) + 1;
            $token_length = $end - $start;
        }

        $line = $phpcsFile->getTokensAsString($start, $token_length);

        // Remove line breaks, Compact multiple spaces into one
        $line = str_replace("\n", ' ', $line);
        $line = preg_replace('/  +/', ' ', $line);

        foreach (static::REG_EXPRESSIONS as $pattern) {
            if (preg_match('/' . $pattern . '/', $line, $matches)) {
                array_shift($matches);

                $phpcsFile->addWarning(
                    sprintf(static::ERROR_MESSAGE, ...$matches),
                    $stackPtr,
                    static::ERROR_NAME
                );
                break;
            }
        }
    }

    /**
     * Depending on the sniff, you can ignore even running the regular expressions if the token is not the right match,
     * e.g. a function name.
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     * @return bool
     */
    protected function isTokenIgnored(\PHP_CodeSniffer\Files\File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        if ($this->isIgnoredContent($token)) {
            return true;
        }

        return false;
    }

    /**
     * Based on the current token content, we can optionally ignore this sniff
     * @param array $token Varying array of data, depends on what token we're at in the stack.
     * @return bool
     */
    protected function isIgnoredContent(array $token): bool
    {
        return false;
    }
}
