<?php

namespace TumblrApp\Sniffs\Commenting;

use TumblrApp\Sniffs\Base\RegExp\RegExp;

/**
 * Ensure @expectedException is no longer used
 */
class TestExpectedExceptionSniff extends RegExp
{
    /** @var array List of regular expressions to look for. Override this in the base class */
    const REG_EXPRESSIONS = [
        '@expectedException([^\s]*)'
    ];

    /** @var string Error message to print when warning occurs */
    const ERROR_MESSAGE = '@expectedException%1$s is deprecated in PHPUnit. Please use $this->expectException%1$s()';

    /** @var string Error name, for use in rule exclusions */
    const ERROR_NAME = 'expectedException';

    /** @var bool This sniff only works in a single line */
    const SINGLE_LINE = true;

    /** @inheritDoc */
    public function register()
    {
        return [
            T_DOC_COMMENT_TAG,
        ];
    }

    /**
     * @inheritDoc
     */
    public function process(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        // Only listen in unit test files
        if (!preg_match('/Test.php$/', $phpcsFile->getFilename())) {
            return;
        }

        parent::process($phpcsFile, $stackPtr);
    }
}
