<?php

namespace TumblrApp\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * A sniff to verify there is no leading whitespace before the <?php tag
 */
class LeadingWhitespaceSniff implements Sniff
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_OPEN_TAG];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $whitespace_before_open_tag = "";
		$token_count = is_countable($tokens) ? count($tokens) : 0;
        for ($i = 0, $len = $token_count; $i < $len; $i++) {
            $token = $tokens[$i];
            if ($token["code"] === T_OPEN_TAG) {
                break;
            }
            $content = $token["content"];

            // if the content starts with a shebang, remove it
            if (strpos($content, "#!") === 0) {
                $end = strpos($content, PHP_EOL);
                $content = substr($content, $end + 1);
            }

            $whitespace_before_open_tag .= $content;
        }

        if ($whitespace_before_open_tag !== "") {
            $phpcsFile->addError("whitespace detected before php open tag", $i - 1, "LeadingWhitespace");
        }
    }
}
