<?php

namespace TumblrApp\Sniffs\Base;

/**
 * Trait which adds method argument parsing support to any sniff you want
 */
trait MethodArgumentSniffer
{
    /**
     * Given a starting index of an open parenthesis, returns the "arguments" between it and the matching closing paren.
     * Supports nested parenthesis.
     *
     * e.g. Given:
     *              |
     *              |
     *              v
     * $this->foobar(1, action(3, 'foo'), 5)
     *
     * Will return the arguments as strings:
     *
     * $args = [
     *     '1',
     *     "action(3, 'foo')",
     *     '5',
     * ];
     *
     * @param int $start_paren Index of starting parenthesis
     * @param array $tokens Token list in file
     * @param \PHP_CodeSniffer\Files\File $phpcsFile PHPCS file
     * @return array
     */
    public function getMethodArguments(int $start_paren, array $tokens, \PHP_CodeSniffer\Files\File $phpcsFile): array
    {
        $ptr = $start_paren;
        $end_paren = (int) $tokens[$start_paren]['parenthesis_closer'];
        $args = [];
        $current_arg = '';
        $ptr++;
        while ($ptr !== $end_paren) {
            $bracket = null;
            $closer_name = null;
            if ($tokens[$ptr]['content'] === '(') {
                $bracket = '(';
                $closer_name = 'parenthesis_closer';
            } elseif ($tokens[$ptr]['content'] === '[') {
                $bracket = '[';
                $closer_name = 'bracket_closer';
            }
            if ($bracket) {
                // Nested brackets, skip over until closing brackets
                $current_arg .= $phpcsFile->getTokensAsString(
                    $ptr,
                    $tokens[$ptr][$closer_name] - $ptr + 1
                );
                $ptr = $tokens[$ptr][$closer_name] + 1;
                continue;
            }
            if ($tokens[$ptr]['content'] === ',') {
                // We finished an argument, add it and reset
                $args[] = trim($current_arg);
                $current_arg = '';
                $ptr++;
                continue;
            }
            $current_arg .= $tokens[$ptr]['content'] ?? '';
            $ptr++;
        }

        if ($current_arg) {
            // Add the last argument
            $args[] = trim($current_arg);
        }

        return $args;
    }
}
