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

namespace Graze\ParallelProcess\Test\Unit\Display;

use Graze\ParallelProcess\Display\TinyProgressBar;
use Graze\ParallelProcess\Test\TestCase;

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
        $format = '▕{bar}▏{perc} {position}/{max}',
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
        return [
            [3, 10, 100, '▕▍  ▏ 10% 10/100'],
            [3, 1, 100, '▕▏  ▏  1% 1/100'], // anything over 0 will display something
            [3, 0, 100, '▕   ▏  0% 0/100'],
            [3, 100, 100, '▕███▏100% 100/100'],
            [3, 99, 100, '▕██▉▏ 99% 99/100'], // 99% will display less than 100%
            [5, 20, 100, '▕█    ▏ 20% 20/100'],
            [3, 0.3, 1, '▕▉  ▏ 30% 0.3/1'],
            [10, 12.5, 120, '▕█         ▏ 10% 12.5/120'],
            [3, -10, 100, '▕   ▏  0% 0/100'], // position must be greater than or equal to than 0
            [3, 120, 100, '▕███▏100% 100/100'], // position must be less than or equal to max
            [3, 100, 100, '▕███▏100%', '▕{bar}▏{perc}'],
            [3, 100, 100, '▕███▏', '▕{bar}▏'],
            [3, 100, 100, '███', '{bar}'],
            [3, 100, 100, '100/100', '{position}/{max}'],
            [3, 100, 100, '100%', '{perc}'],
            [10, 15, 100, '▕█▄        ▏ 15% 15/100', '▕{bar}▏{perc} {position}/{max}', [" ", "▄", "█"]],
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
            [-9223372036854775808],
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
            [-9223372036854775808],
        ];
    }

    public function testAccessors()
    {
        $bar = new TinyProgressBar(5);

        $this->assertEquals(0, $bar->getPosition());
        $this->assertEquals(100, $bar->getMax());
        $this->assertEquals(5, $bar->getLength());
        $this->assertEquals(TinyProgressBar::FORMAT_DEFAULT, $bar->getFormat());

        $this->assertSame($bar, $bar->setPosition(5));
        $this->assertSame($bar, $bar->setMax(200));
        $this->assertSame($bar, $bar->setLength(10));
        $this->assertSame($bar, $bar->setFormat(TinyProgressBar::FORMAT_SHORT));

        $this->assertEquals(5, $bar->getPosition());
        $this->assertEquals(200, $bar->getMax());
        $this->assertEquals(10, $bar->getLength());
        $this->assertEquals(TinyProgressBar::FORMAT_SHORT, $bar->getFormat());
    }

    public function testAdvance()
    {
        $bar = new TinyProgressBar(5);

        $this->assertEquals(0, $bar->getPosition());

        $this->assertSame($bar, $bar->advance());
        $this->assertEquals(1, $bar->getPosition());

        $this->assertSame($bar, $bar->advance(3));
        $this->assertEquals(4, $bar->getPosition());

        $this->assertSame($bar, $bar->advance(0.2));
        $this->assertEquals(4.2, $bar->getPosition(), '', 0.00001);
    }
}
