<?php

namespace TumblrApp\Sniffs\NamingConventions;

/**
 * Sniff to detect classes that extend 'PHPUnit_FrameworkTestCase' but that do not have a name that ends with 'Test'.
 */
class TestCaseNameSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_CLASS];
    }

    /**
     * @inheritDoc
     */
    public function process(\PHP_CodeSniffer\Files\File $phpcsFile, $stackPtr)
    {
        // does this class extend something that ends with 'PHPUnit_Framework_TestCase'?
        $extendedClassName = $phpcsFile->findExtendedClassName($stackPtr);
        if (!preg_match('/PHPUnit_Framework_TestCase$/i', $extendedClassName)) {
            // nope! which means we don't care about this class.
            return;
        }

        // is this class abstract?
        $classProperties = $phpcsFile->getClassProperties($stackPtr);
        if ($classProperties['is_abstract']) {
            // yes! which means we don't care about this class.
            return;
        }

        // does this class have a name that ends with 'Test'?
        $className = $phpcsFile->getDeclarationName($stackPtr);
        if (preg_match('/Test$/', $className)) {
            // yes! which means we're all good.
            return;
        }

        // if we've reached here then things are NOT OK. add a lint error.
        $phpcsFile->addError(
            'Name of class extending "PHPUnit_Framework_TestCase" must end with "Test"',
            $stackPtr,
            'TestCaseName'
        );
    }
}
