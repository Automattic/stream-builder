<?php

namespace TumblrApp\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * A sniff for locating/complaining about using continue statments when
 * targeting a switch statement
 *
 * @see https://www.php.net/manual/en/migration73.incompatible.php#migration73.incompatible.core.continue-targeting-switch
 */
class SwitchContinueSniff implements Sniff
{
    /**
     * A map of tokens that indicate the start of a block that should be
     * skipped. Note that we include `switch` itself to skip over nested
     * switch blocks, but the nested blocks will be processed separately.
     *
     * @var array<int,bool>
     */
    private const SKIP_SCOPE_START_TOKENS = [
        T_FOR => true,
        T_FOREACH => true,
        T_WHILE => true,
        T_DO => true,
        T_SWITCH => true,
    ];

    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_SWITCH];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // the switch token conveniently has the indexes for the start and
        // end of the switch block
        $switch_token = $tokens[$stackPtr];
        $i = $switch_token["scope_opener"];
        $len = $switch_token["scope_closer"];

        // iterate through all the tokens, and add an error if we find
        // a `continue` that is followed by a semicolon
        for (; $i < $len; $i++) {
            // if we encounter a loop block within the switch block, skip
            // to the end of the loop block
            if (isset(self::SKIP_SCOPE_START_TOKENS[$tokens[$i]["code"]])) {
                $i = $tokens[$i]["scope_closer"];
                continue;
            }

            if ($tokens[$i]["code"] !== T_CONTINUE) {
                continue;
            }

            if ($tokens[$i + 1]["code"] === "PHPCS_T_SEMICOLON") {
                $msg = "use 'break' instead of 'continue' when targeting 'switch'";
                $phpcsFile->addError($msg, $i, 'ContinueTargetingSwitch');
            }
        }
    }
}
