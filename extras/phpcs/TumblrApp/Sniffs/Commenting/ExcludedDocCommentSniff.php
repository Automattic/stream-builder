<?php

namespace TumblrApp\Sniffs\Commenting;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Parses and verifies the doc comments for interfaces.
 */
class ExcludedDocCommentSniff implements Sniff
{
    private const EXCLUDED_DOC_TAGS = [
        '@package' => 'Package is derived from the namespace, no need for @package doc notation.',
        '@doesNotPerformAssertions' => 'Tests without assertions are not allowed, remove @doesNotPerformAssertions',
    ];


    /**
     * Case insenstitive search to detect if a string contains the given substring.
     *
     * @param string $haystack The string to check for the substring.
     * @param string $needle The substring to check for.
     * @param bool $multibyte Whether or not to use multibyte character safe checking.
     * @return bool
     */
    public static function icontains($haystack, string $needle, bool $multibyte = false): bool
    {
        return $multibyte ?
            (mb_stripos($haystack, $needle, 0, 'UTF-8') !== false) :
            (stripos($haystack, $needle) !== false);
    }

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_INTERFACE, T_TRAIT, T_CLASS, T_ABSTRACT, T_FUNCTION, T_NAMESPACE];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $find = Tokens::$methodPrefixes;
        $find[] = T_WHITESPACE;

        do {
            $token = $phpcsFile->findPrevious($find, (--$stackPtr), null, true);
            $token_code = $tokens[$token]['code'];
            $token_value = $tokens[$token]['content'];
            if (
                $token_code === T_DOC_COMMENT_TAG
                && isset(self::EXCLUDED_DOC_TAGS[$token_value])
            ) {
                $phpcsFile->addError(self::EXCLUDED_DOC_TAGS[$token_value], $token, 'ExcludeBannedTags');
            }
        } while (self::icontains($token_code, 'COMMENT'));
    }
}
