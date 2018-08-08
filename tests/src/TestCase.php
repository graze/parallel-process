<?php

/**
 * This file is part of graze/parallel-process.
 *
 * Copyright © 2018 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

namespace Graze\ParallelProcess\Test;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use MockeryPHPUnitIntegration;

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

        $expectedCount = count($expected);
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->assertSameSize($expected[$i], $actual[$i]);
            $expectedChildCount = count($expected[$i]);
            for ($j = 0; $j < $expectedChildCount; $j++) {
                $this->assertRegExp($expected[$i][$j], $actual[$i][$j], sprintf('group: %d, line: %d', $i + 1, $j + 1));
            }
        }
    }
}
