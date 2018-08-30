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

namespace Graze\ParallelProcess;

use Exception;
use Graze\ParallelProcess\Event\EventDispatcherTrait;
use Graze\ParallelProcess\Event\RunEvent;
use Throwable;

class CallbackRun implements RunInterface, OutputterInterface
{
    use EventDispatcherTrait;

    /** @var callable */
    private $callback;
    /** @var float */
    private $started = 0;
    /** @var float */
    private $finished = 0;
    /** @var bool */
    private $successful = false;
    /** @var string[] */
    private $tags;
    /** @var Exception|null */
    private $exception = null;
    /** @var string */
    private $last;
    /** @var float */
    private $priority;

    /**
     * Run constructor.
     *
     * @param callable $callback A callback to run, if this returns a string, it can be accessed from the
     *                           `->getLastMessage()` calls
     * @param string[] $tags     List of key value tags associated with this run
     * @param float    $priority
     */
    public function __construct(callable $callback, array $tags = [], $priority = 1.0)
    {
        $this->callback = $callback;
        $this->tags = $tags;
        $this->priority = $priority;
    }

    /**
     * @param float $priority
     *
     * @return CallbackRun
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return string[]
     */
    protected function getEventNames()
    {
        return [
            RunEvent::STARTED,
            RunEvent::COMPLETED,
            RunEvent::FAILED,
            RunEvent::UPDATED,
        ];
    }

    /**
     * Start the process
     *
     * @return $this
     */
    public function start()
    {
        if ($this->started == 0) {
            $this->started = microtime(true);
            $this->dispatch(RunEvent::STARTED, new RunEvent($this));
            try {
                $output = call_user_func($this->callback);
                $this->handleOutput($output);
                $this->finished = microtime(true);
                $this->successful = true;
                $this->dispatch(RunEvent::COMPLETED, new RunEvent($this));
            } catch (Exception $e) {
                $this->finished = microtime(true);
                $this->successful = false;
                $this->exception = $e;
                $this->dispatch(RunEvent::FAILED, new RunEvent($this));
            }
        }

        return $this;
    }

    /**
     * @param mixed $output The output from the callback, if you want to send this back, return a string|string[]
     */
    private function handleOutput($output)
    {
        if (is_string($output)) {
            $output = explode("\n", $output);
        }
        if (is_array($output)) {
            foreach ($output as $line) {
                if (is_string($line)) {
                    $line = rtrim($line);
                    if (mb_strlen($line) > 0) {
                        $this->last = $line;
                        $this->dispatch(RunEvent::UPDATED, new RunEvent($this));
                    }
                }
            }
        }
    }

    /**
     * Poll to see if the callback is still running (hint: it is not)
     *
     * @return bool
     */
    public function poll()
    {
        // non async process, so it will have finished when calling start
        return false;
    }

    /**
     * Return if the underlying process is running
     *
     * @return bool
     */
    public function isRunning()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->successful;
    }

    /**
     * @return bool
     */
    public function hasStarted()
    {
        return $this->started > 0;
    }

    /**
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @return float number of seconds this run has been running for (0 for not started)
     */
    public function getDuration()
    {
        if ($this->finished > 0) {
            return $this->finished - $this->started;
        }
        return $this->started > 0 ? microtime(true) - $this->started : 0;
    }

    /**
     * @return float[]|null the process between 0 and 1 if the run supports it, otherwise null
     */
    public function getProgress()
    {
        return null;
    }

    /**
     * If the run was unsuccessful, get the error if applicable
     *
     * @return Exception[]|Throwable[]
     */
    public function getExceptions()
    {
        if ($this->exception !== null) {
            return [$this->exception];
        }
        return [];
    }

    /**
     * Get the last message that this thing produced
     *
     * @return string
     */
    public function getLastMessage()
    {
        return $this->last;
    }

    /**
     * @return string
     */
    public function getLastMessageType()
    {
        return '';
    }

    /**
     * @return float The priority for this run, where the larger the number the higher the priority
     */
    public function getPriority()
    {
        return $this->priority;
    }
}
