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

use Graze\DiffRenderer\DiffConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

class BufferDiffOutput extends DiffConsoleOutput
{
    /** @var array */
    protected $written;

    /**
     * BufferDiffOutput constructor.
     */
    public function __construct()
    {
        $dummy = new ConsoleOutput();
        parent::__construct($dummy);
    }

    /**
     * @param array|string $messages
     * @param bool         $newline
     * @param int          $options
     */
    public function write($messages, $newline = false, $options = 0)
    {
        $messages = (array) $messages;
        $this->written[] = $messages;
    }

    /**
     * @param array|string $messages
     * @param int          $options
     */
    public function writeln($messages, $options = 0)
    {
        $messages = (array) $messages;
        $this->written[] = $messages;
    }

    /**
     * @param string|\string[] $messages
     * @param bool             $newline
     * @param int              $options
     */
    public function reWrite($messages, $newline = false, $options = 0)
    {
        $messages = (array) $messages;
        $this->written[] = $messages;
    }

    /**
     * @return array
     */
    public function getWritten()
    {
        return $this->written;
    }
}
