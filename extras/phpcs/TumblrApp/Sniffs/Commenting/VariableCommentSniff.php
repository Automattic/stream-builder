<?php

namespace TumblrApp\Sniffs\Commenting;

use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\VariableCommentSniff as SquizVariableCommentSniff;
use PHP_CodeSniffer\Util\Common;

/**
 * Overrides SquizLabs' variable comment sniff to support custom Tumblr rules.
 */
class VariableCommentSniff extends SquizVariableCommentSniff
{
    /**
     * Called to process class member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function processMemberVar(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        $tokens       = $phpcsFile->getTokens();
        $commentToken = [
            T_COMMENT,
            T_DOC_COMMENT_CLOSE_TAG,
        ];

        $commentEnd = $phpcsFile->findPrevious($commentToken, $stackPtr);
        if ($commentEnd === false) {
            $phpcsFile->addError('Missing member variable doc comment', $stackPtr, 'Missing');
            return;
        }

        if ($tokens[$commentEnd]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a member variable comment', $stackPtr, 'WrongStyle');
            return;
        } elseif ($tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
            $phpcsFile->addError('Missing member variable doc comment', $stackPtr, 'Missing');
            return;
        } else {
            // Make sure the comment we have found belongs to us.
            $commentFor = $phpcsFile->findNext(array(T_VARIABLE, T_CLASS, T_INTERFACE), ($commentEnd + 1));
            if ($commentFor !== $stackPtr) {
                $phpcsFile->addError('Missing member variable doc comment', $stackPtr, 'Missing');
                return;
            }
        }

        $commentStart = $tokens[$commentEnd]['comment_opener'];

        $foundVar = null;
        $foundInherit = null;
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@inheritDoc') {
                $foundInherit = true;
                continue;
            }
            if ($tokens[$tag]['content'] === '@var') {
                if ($foundVar !== null) {
                    $error = 'Only one @var tag is allowed in a member variable comment';
                    $phpcsFile->addError($error, $tag, 'DuplicateVar');
                } else {
                    $foundVar = $tag;
                }
            } elseif ($tokens[$tag]['content'] === '@see') {
                // Make sure the tag isn't empty.
                $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
                if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                    $error = 'Content missing for @see tag in member variable comment';
                    $phpcsFile->addError($error, $tag, 'EmptySees');
                }
            } else {
                $error = '%s tag is not allowed in member variable comment';
                $data  = array($tokens[$tag]['content']);
                $phpcsFile->addWarning($error, $tag, 'TagNotAllowed', $data);
            }
        }

        if ($foundVar && $foundInherit) {
            $error = 'Found @var along with @inheritDoc in member variable comment';
            $phpcsFile->addError($error, $commentEnd, 'VarWithInherit');
            return;
        }

        // The @var tag is the only one we require.
        if (!$foundVar && !$foundInherit) {
            $error = 'Missing @var tag in member variable comment';
            $phpcsFile->addError($error, $commentEnd, 'MissingVar');
            return;
        }

        if (!$foundVar) {
            return;
        }

        $firstTag = $tokens[$commentStart]['comment_tags'][0];
        if ($foundVar !== null && $tokens[$firstTag]['content'] !== '@var') {
            $error = 'The @var tag must be the first tag in a member variable comment';
            $phpcsFile->addError($error, $foundVar, 'VarOrder');
        }

        // Make sure the tag isn't empty and has the correct padding.
        $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $foundVar, $commentEnd);
        if ($string === false || $tokens[$string]['line'] !== $tokens[$foundVar]['line']) {
            $error = 'Content missing for @var tag in member variable comment';
            $phpcsFile->addError($error, $foundVar, 'EmptyVar');
            return;
        }

        $varType       = $tokens[($foundVar + 2)]['content'];
        $suggestedType = Common::suggestType($varType);
        if ($varType !== $suggestedType) {
            $error = 'Expected "%s" but found "%s" for @var tag in member variable comment';
            $data  = [
                $suggestedType,
                $varType,
            ];
            $phpcsFile->addError($error, ($foundVar + 2), 'IncorrectVarType', $data);
        }
    }
}
