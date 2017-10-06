<?php

/**
 * This file is part of graze/parallel-process.
 *
 * Copyright (c) 2017 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

namespace Graze\ParallelProcess\Test;

class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Compare the outputs with an expected input.
     *
     * Each element in the array is a call to `write/writeln/reWrite`
     * Each element in the child array is a line to be written
     *
     * @param string[][] $expected Set of regular expressions to match against
     * @param string[][] $actual   The actual output
     */
    protected function compareOutputs(array $expected, array $actual)
    {
        $this->assertSameSize($expected, $actual);

        for ($i = 0; $i < count($expected); $i++) {
            $this->assertSameSize($expected[$i], $actual[$i]);
            for ($j = 0; $j < count($expected[$i]); $j++) {
                $this->assertRegExp($expected[$i][$j], $actual[$i][$j], sprintf('group: %d, line: %d', $i + 1, $j + 1));
            }
        }
    }
}
