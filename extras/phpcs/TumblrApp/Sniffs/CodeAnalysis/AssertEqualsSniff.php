<?php

namespace TumblrApp\Sniffs\CodeAnalysis;

class AssertEqualsSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return array(
            T_STRING,
            T_FUNCTION,
        );
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
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        // Only listen in unit test files
        if (!preg_match('/Test.php$/', $phpcsFile->getFilename())) {
            return;
        }
        if ($token['content'] === 'assertEquals') {
            $phpcsFile->addWarning(
                'assertEquals is not recommended in unit tests, use assertSame if possible ',
                $stackPtr,
                'DetectedAssertEquals'
            );
        }
    }
}
