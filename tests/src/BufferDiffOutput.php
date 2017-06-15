<?php

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
