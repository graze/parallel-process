<?php

namespace Graze\ParallelProcess\Test\Unit;

use Graze\ParallelProcess\Test\TestCase;
use Graze\ParallelProcess\TinyProgressBar;

class TinyProgressBarTest extends TestCase
{
    /**
     * @dataProvider barDataProvider
     *
     * @param int    $length
     * @param float  $position
     * @param float  $max
     * @param string $expected
     * @param string $format
     * @param array  $barChars
     */
    public function testBar(
        $length,
        $position,
        $max,
        $expected,
        $format = '|{bar}| {perc} {position}/{max}',
        array $barChars = []
    ) {
        $bar = new TinyProgressBar($length, $format, $max);

        if (count($barChars) > 0) {
            $bar->setBarCharacters($barChars);
        }

        $bar->setPosition($position);

        $this->assertEquals($expected, $bar->render());
    }

    /**
     * @return array
     */
    public function barDataProvider()
    {
        $standardChars = "▏▎▍▌▋▊▉█";
        return [
            [3, 10, 100, '|▍  |  10% 10/100'],
            [3, 1, 100, '|▏  |   1% 1/100'], // anything over 0 will display something
            [3, 0, 100, '|   |   0% 0/100'],
            [3, 100, 100, '|███| 100% 100/100'],
            [3, 99, 100, '|██▉|  99% 99/100'], // 99% will display less than 100%
            [5, 20, 100, '|█    |  20% 20/100'],
            [3, 0.3, 1, '|▉  |  30% 0.3/1'],
            [10, 12.5, 120, '|█         |  10% 12.5/120'],
            [3, -10, 100, '|   |   0% 0/100'], // position must be greater than or equal to than 0
            [3, 120, 100, '|███| 100% 100/100'], // position must be less than or equal to max
            [3, 100, 100, '|███| 100%', '|{bar}| {perc}'],
            [3, 100, 100, '|███|', '|{bar}|'],
            [3, 100, 100, '███', '{bar}'],
            [3, 100, 100, '100/100', '{position}/{max}'],
            [3, 100, 100, '100%', '{perc}'],
        ];
    }

    /**
     * @dataProvider invalidLengthsData
     * @expectedException \InvalidArgumentException
     *
     * @param int $length
     */
    public function testInvalidLengthWillThrowAnException($length)
    {
        new TinyProgressBar($length);
    }

    /**
     * @return array
     */
    public function invalidLengthsData()
    {
        return [
            [0],
            [-1],
            [PHP_INT_MIN],
        ];
    }

    /**
     * @dataProvider invalidMaxData
     * @expectedException \InvalidArgumentException
     *
     * @param int $max
     */
    public function testInvalidMaximumValuesWillThrowAnException($max)
    {
        new TinyProgressBar(3, TinyProgressBar::FORMAT_DEFAULT, $max);
    }

    /**
     * @return array
     */
    public function invalidMaxData()
    {
        return [
            [0],
            [-1],
            [PHP_INT_MIN],
        ];
    }
}
