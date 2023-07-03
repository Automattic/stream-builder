<?php

namespace TumblrApp\Sniffs\Commenting;

use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\FunctionCommentSniff as SquizFunctionCommentSniff;
use PHP_CodeSniffer\Util\Common;

/**
 * A Tumblr rule specific override for Squiz_Sniffs_Commenting_FunctionCommentSniff.
 * Parses and verifies the doc comments for functions.
 */
class FunctionCommentSniff extends SquizFunctionCommentSniff
{
    /**
     * Construct the instance
     */
    public function __construct()
    {
        Common::$allowedTypes = [
            'array',
            'bool',
            'boolean',
            'float',
            'int',
            'integer',
            'mixed',
            'object',
            'string',
            'resource',
            'callable',
        ];
    }

    /**
     * Process the return comment of this function comment.
     * Check for presence of @ inheritDoc and if @ return exists as well.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processReturn(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        $hasInheritDoc = false;
        $hasReturn = false;

        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@return') {
                $hasReturn = true;
            } elseif ($tokens[$tag]['content'] === '@inheritDoc') {
                if ($hasInheritDoc) {
                    $error = 'Cannot have more than one @inheritDoc tag in function comment';
                    $phpcsFile->addError($error, $stackPtr, 'HasMultipleInheritDocTags');
                }
                $hasInheritDoc = true;
            }
        }

        if ($hasReturn && $hasInheritDoc) {
            $error = 'Cannot have @return tag in function comment when @inheritDoc present';
            $phpcsFile->addError($error, $stackPtr, 'HasInheritDocAndReturnTag');
            return;
        }

        if ($hasInheritDoc) {
            return;
        }

        parent::processReturn($phpcsFile, $stackPtr, $commentStart);
    }

    /**
     * Process the function parameter comments.
     * Detect usage of @ inheritDoc to override other requirements. Call the parent otherwise.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processParams(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        $hasInheritDoc = false;
        $hasParam = false;

        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            // Allow @inheritdoc AND @inheritDoc
            if ($tokens[$tag]['content'] === '@inheritdoc' || $tokens[$tag]['content'] === '@inheritDoc') {
                if ($hasInheritDoc) {
                    $error = 'Cannot have more than one @inheritDoc tag in function comment';
                    $phpcsFile->addError($error, $stackPtr, 'HasMultipleInheritDocTags');
                }
                $hasInheritDoc = true;
            } elseif ($tokens[$tag]['content'] === '@param') {
                $hasParam = true;
            }
        }

        if ($hasInheritDoc && $hasParam) {
            $error = 'Cannot have @param tag when @inheritDoc tag is present in function comment';
            $phpcsFile->addError($error, $stackPtr, 'HasInheritDocAndParamTag');
        }

        if ($hasInheritDoc) {
            return;
        }

        parent::processParams($phpcsFile, $stackPtr, $commentStart);
    }
}
