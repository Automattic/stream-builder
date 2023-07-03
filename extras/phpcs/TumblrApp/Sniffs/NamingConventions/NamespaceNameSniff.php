<?php

namespace TumblrApp\Sniffs\NamingConventions;

use \PHP_CodeSniffer\Files;
use \PHP_CodeSniffer\Sniffs;

/**
 * Sniff to detect namespaces that do not follow PascalCase
 */
class NamespaceNameSniff implements Sniffs\Sniff
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_NAMESPACE];
    }

    /**
     * @inheritDoc
     */
    public function process(Files\File $phpcsFile, $stackPtr)
    {
        $namespace = $this->findNamespaceName($phpcsFile, $stackPtr);

        if (!$this->namespaceIsValid($namespace)) {
            $this->addError($phpcsFile, $stackPtr);
        }
    }

    private function namespaceIsValid(string $namespace): bool
    {
        // Non-alphanumerics are forbidden. That includes spaces and underscore.
        if (preg_match('/[^\\\A-Za-z0-9]/', $namespace)) {
            return false;
        }

        // Words must start with uppercase characters, e.g. Foo\bar is not allowed.
        if (preg_match('/(^|\\\)[a-z]/', $namespace)) {
            return false;
        }

        return true;
    }

    /**
     * Adds a common error.
     *
     * @param Files\File $phpcsFile
     * @param int $stackPtr
     */
    private function addError(Files\File $phpcsFile, int $stackPtr): void
    {
        $phpcsFile->addError(
            'Namespaces must follow PascalCase naming style',
            $stackPtr,
            'NamespaceName'
        );
    }

    /**
     * Finds namespace name at given stack pointer.
     *
     * @param Files\File $phpcsFile
     * @param int $stackPtr
     * @return string
     */
    private function findNamespaceName(Files\File $phpcsFile, int $stackPtr): string
    {
        $start_from = $phpcsFile->findNext(T_STRING, $stackPtr);
        $token_length = $phpcsFile->findNext([
            T_SEMICOLON,
            T_OPEN_CURLY_BRACKET,
        ], $stackPtr) - $start_from - 1;

        return $phpcsFile->getTokensAsString($start_from, $token_length);
    }
}
