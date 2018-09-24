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
use Graze\ParallelProcess\Event\PriorityChangedEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Throwable;

class CallbackRun implements RunInterface, OutputterInterface, PrioritisedInterface
{
    use EventDispatcherTrait;
    use RunningStateTrait;
    use PrioritisedTrait;

    /** @var callable */
    private $callback;
    /** @var bool */
    private $successful = false;
    /** @var string[] */
    private $tags;
    /** @var Exception|Throwable|null */
    private $exception = null;
    /** @var string */
    private $last;

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
     * @return string[]
     */
    protected function getEventNames()
    {
        return [
            RunEvent::STARTED,
            RunEvent::COMPLETED,
            RunEvent::SUCCESSFUL,
            RunEvent::FAILED,
            RunEvent::UPDATED,
            PriorityChangedEvent::CHANGED,
        ];
    }

    /**
     * Start the process
     *
     * @return $this
     */
    public function start()
    {
        if (!$this->hasStarted()) {
            $this->setStarted();
            $this->dispatch(RunEvent::STARTED, new RunEvent($this));
            try {
                try {
                    $output = call_user_func($this->callback);
                    $this->handleOutput($output);
                    $this->setFinished();
                    $this->successful = true;
                    $this->dispatch(RunEvent::SUCCESSFUL, new RunEvent($this));
                } catch (Exception $e) {
                    $this->setFinished();
                    $this->successful = false;
                    $this->exception = $e;
                    $this->dispatch(RunEvent::FAILED, new RunEvent($this));
                } catch (Throwable $e) {
                    $this->setFinished();
                    $this->successful = false;
                    $this->exception = $e;
                    $this->dispatch(RunEvent::FAILED, new RunEvent($this));
                }
            } finally {
                $this->dispatch(RunEvent::COMPLETED, new RunEvent($this));
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
        return $this->getState() !== RunInterface::STATE_NOT_STARTED;
    }

    /**
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
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
}
