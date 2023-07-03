<?php

namespace TumblrApp\Sniffs\CodeAnalysis;

/**
 * Looks for usages of == in direct string comparisons, e.g. if ($foo == 'horse')
 */
class DoubleEqualsSniff extends \TumblrApp\Sniffs\Base\RegExp\RegExp
{
    const REG_EXPRESSIONS = [
        '(!=|[^=!]==) ?[\'"]([^\'"]+)[\'"]',
        '[\'"]([^\'"]+)[\'"] ?(!=|==)[^=]',
    ];

    /** @var string Error message to print when warning occurs */
    const ERROR_MESSAGE = 'Please always use [`===` or `!==`](http://yo/doubleEqualsCompare) ' .
        'when doing string comparisons.';

    /** @var string Error name, for use in rule exclusions */
    const ERROR_NAME = 'DoubleEqualsStringCompare';

    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return array(
            T_IS_EQUAL,
            T_IS_NOT_EQUAL,
        );
    }
}
