<?php

namespace Graze\ParallelProcess\Test;

use Psr\Log\AbstractLogger;

class BufferedLogger extends AbstractLogger
{
    /** @var array */
    private $logs;

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->logs[] = [$level, $message, $context];
    }

    /**
     * @return array
     */
    public function cleanLogs()
    {
        $logs = $this->logs;
        $this->logs = [];
        return $logs;
    }
}
