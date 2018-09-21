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

namespace Graze\ParallelProcess\Display;

use InvalidArgumentException;

class TinyProgressBar
{
    /**
     * Format for a progress bar
     *
     * Options:
     *  - `{bar}` The actual bar
     *  - `{perc}` Display the percentage e.g 2%
     *  - `{position}` Display the current position e.g 1
     *  - `{max}` Display the maximum possible value e.g 100
     *
     * Example formats:
     *  - `{bar} {position}/{max} {perc}`
     */
    const FORMAT_DEFAULT         = '▕<comment>{bar}</comment>▏<info>{perc}</info> {position}/{max}';
    const FORMAT_SHORT           = '▕<comment>{bar}</comment>▏<info>{perc}</info>';
    const FORMAT_BAR_ONLY        = '{bar}';
    const FORMAT_COLOUR_BAR_ONLY = '<comment>{bar}</comment>';

    /** @var int */
    private $length;
    /** @var string */
    private $format;
    /** @var float */
    private $position;
    /** @var float */
    private $max;
    /** @var string[] */
    private $bar;

    /**
     * TinyProgressBar constructor.
     *
     * @param int    $numChars The number of characters to use for the bar
     * @param string $format   The format for the bar
     * @param float  $max      The maximum value for a bar
     */
    public function __construct($numChars, $format = self::FORMAT_DEFAULT, $max = 100.0)
    {
        $this->setLength($numChars);
        $this->setMax($max);
        $this->format = $format;
        $this->position = 0;
        $this->bar = [" ", "▏", "▎", "▍", "▌", "▋", "▊", "▉", "█"];
    }

    /**
     * Sets the characters to use for a bar. A bar is n chars long (defined in the constructor), each char will use
     * each character in the array once.
     *
     * The first character should always be blank
     *
     * Example:
     *
     *     ->setBarCharacters([" ","▄","█"]);
     *
     *     ▄
     *
     *     █
     *
     *     █▄
     *
     *     ██
     *
     *     ██▄
     *
     *     ███
     *
     * @param array $characters
     *
     * @return $this
     */
    public function setBarCharacters(array $characters)
    {
        $this->bar = $characters;
        return $this;
    }

    /**
     * @param float $step
     *
     * @return $this
     */
    public function advance($step = 1.0)
    {
        if ($this->position < $this->max) {
            $this->position = min($this->position + $step, $this->max);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function render()
    {
        $percentage = round($this->position / $this->max, 2);

        // number of chars to display
        $displayChars = ceil($percentage * $this->length);
        if ($displayChars == 0) {
            $displayChars = 1;
        }

        // normalise the percentage to this character
        $percentageStep = 1 / $this->length;
        $minPercentage = $percentageStep * ($displayChars - 1);
        $maxPercentage = $minPercentage + $percentageStep;
        $charPercentage = ($percentage - $minPercentage) / ($maxPercentage - $minPercentage);
        if ($charPercentage == 0) {
            $charIndex = 0;
        } else {
            $charIndex = (int) (floor((count($this->bar) - 2) * $charPercentage) + 1);
        }
        $char = $this->bar[$charIndex];

        $bar = sprintf(
            '%s%s%s',
            str_repeat(end($this->bar), $displayChars - 1),
            $char,
            str_repeat(' ', $this->length - $displayChars)
        );

        $percentageText = sprintf('%3d%%', $percentage * 100);

        return str_replace(
            ['{bar}', '{perc}', '{position}', '{max}'],
            [$bar, $percentageText, $this->position, $this->max],
            $this->format
        );
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param int $length
     *
     * @return $this
     */
    public function setLength($length)
    {
        if ($length <= 0) {
            throw new InvalidArgumentException(sprintf('Supplied bar length: %d must be greater than 0', $length));
        }
        $this->length = $length;
        return $this;
    }

    /**
     * @return float
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * @param float $max
     *
     * @return $this
     */
    public function setMax($max)
    {
        if ($max <= 0) {
            throw new InvalidArgumentException(sprintf('Supplied max value: %d must be greater than 0', $max));
        }
        $this->max = $max;
        return $this;
    }

    /**
     * @return float
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @param string $format
     *
     * @return $this
     */
    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * @param float $position
     *
     * @return TinyProgressBar
     */
    public function setPosition($position)
    {
        $this->position = max(min($position, $this->max), 0);
        return $this;
    }
}
