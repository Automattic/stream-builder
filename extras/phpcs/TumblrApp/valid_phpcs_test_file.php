<?php

/**
 * Class Test_Class
 * Copy this file to ../../../ to run and test it with phpcs
 * No errors should be reported
 */
class Valid_Class extends \Nothing
{
    /**
     * Test function call
     * @param int $x The X value
     * @param float $y The Y coordinate
     * @return int
     */
    public function valid_function_call(int $x, float $y): int
    {
        $matrix = [
            [4, 5, 6],
            [7, 8, 9],
            [1, 2, 3],
        ];
        $matrix2 = [
            'foo',     // First value
            'barth',   // Second value
            'newyork', // Third value
        ];
        $matrix3 = [1, 3, 8, 9, 10];
        $matrix4 = [
            'one-liner',
        ];

        $result = array_merge($matrix, $matrix2, $matrix3, $matrix4);
        return $x * (int) $y * $result[0][0];
    }

    /**
     * @return callable
     */
    public function custom_closure(): callable
    {
        return function ($x) {
            return $x * $x;
        };
    }
}
