<?php

namespace TumblrApp\Sniffs\CodeAnalysis;

/**
 * Detects and warns about usage of codingStandardsIgnoreStart. It's not strictly forbidden but should be used
 * sparingly.
 */
class CodeStandardsIgnoreSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return array(
            T_COMMENT,
            T_DOC_COMMENT,
            T_DOC_COMMENT_STRING,
            T_DOC_COMMENT_TAG,
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

        if (strpos($token['content'], 'codingStandardsIgnoreStart')) {
            $phpcsFile->addWarningOnLine(
                'Usage of @codingStandardsIgnoreStart is discouraged in most cases (found on next line).'
                    . ' Review needed: @Automattic/stream-builders',
                $token['line'] - 1,
                'DetectedCodingStandardsIgnoreStart'
            );
        }
    }
}
