<?php

namespace TumblrApp\Sniffs\CodeAnalysis;

use TumblrApp\Sniffs\Base\MethodArgumentSniffer;

/**
 * Looks for usages of mb_* string functions without the $encoding parameter
 */
class StrictMbStringSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{
    use MethodArgumentSniffer;

    /** @var string Error message to print when warning occurs */
    private const ERROR_MESSAGE = 'If possible, please consider using the %s parameter of ' .
        '`%s` ($encoding) which should be set to `UTF-8`, unless you have a good reason not to.';

    /** @var string Error name, for use in rule exclusions */
    private const ERROR_NAME = 'NonStrictMB';

    /** @var array List of mb_ string fuctions to check for and the position (non zero based) of the encoding param */
    private static $mb_functions_to_check = [
        'mb_strtolower'   => ['encoding_param_pos' => 2],
        'mb_strtoupper'   => ['encoding_param_pos' => 2],
        'mb_stripos'      => ['encoding_param_pos' => 4],
        'mb_strstr'       => ['encoding_param_pos' => 4],
        'mb_substr'       => ['encoding_param_pos' => 4],
        'mb_strlen'       => ['encoding_param_pos' => 2],
        'mb_convert_case' => ['encoding_param_pos' => 3],
        'mb_strcut'       => ['encoding_param_pos' => 4],
        'mb_stristr'      => ['encoding_param_pos' => 4],
        'mb_strrpos'      => ['encoding_param_pos' => 4],
        'mb_substr_count' => ['encoding_param_pos' => 3],
    ];

    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [
            T_STRING,
        ];
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
        $token = $tokens[$stackPtr];

        $func_name = $token['content'];
        if (!isset(self::$mb_functions_to_check[$func_name])) {
            // only listen for mb_* statements
            return;
        }

        $args = $this->getMethodArguments(
            $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr),
            $tokens,
            $phpcsFile
        );

        $encoding_param_pos = self::$mb_functions_to_check[$func_name]['encoding_param_pos'];

        // If the argument is not set
        if (!isset($args[$encoding_param_pos - 1])) {
            $phpcsFile->addWarning(
                sprintf(static::ERROR_MESSAGE, self::getOrdinal($encoding_param_pos), $token['content']),
                $stackPtr,
                static::ERROR_NAME
            );
        }
    }

    /**
     * Return an oridnal string for a given number
     *
     * @param int $number The number to convert to an ordinal
     *
     * @return string
     */
    private function getOrdinal(int $number): string
    {
        switch ($number) {
            case 1:
                return "{$number}st";
            case 2:
                return "{$number}nd";
            case 3:
                return "{$number}rd";
            default:
                return "{$number}th";
        }
    }
}
