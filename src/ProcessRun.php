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
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessRun implements RunInterface, OutputterInterface, PrioritisedInterface
{
    use EventDispatcherTrait;
    use RunningStateTrait;
    use PrioritisedTrait;

    /** @var Process */
    private $process;
    /** @var bool */
    private $successful = false;
    /** @var bool */
    private $completed = false;
    /** @var string */
    private $last = '';
    /** @var string */
    private $lastType = 'std';
    /** @var bool */
    private $updateOnPoll = true;
    /** @var bool */
    private $updateOnProcessOutput = true;
    /** @var string[] */
    private $tags;

    /**
     * Run constructor.
     *
     * @param Process  $process
     * @param string[] $tags List of key value tags associated with this run
     * @param float    $priority
     */
    public function __construct(Process $process, array $tags = [], $priority = 1.0)
    {
        $this->process = $process;
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
            RunEvent::FAILED,
            RunEvent::UPDATED,
            RunEvent::SUCCESSFUL,
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
        if (!$this->process->isStarted()) {
            $this->setStarted();
            $this->dispatch(RunEvent::STARTED, new RunEvent($this));
            $this->process->start(
                function ($type, $data) {
                    $this->lastType = $type;
                    foreach (explode("\n", $data) as $line) {
                        $line = rtrim($line);
                        if (mb_strlen($line) > 0) {
                            $this->last = $line;
                            if ($this->updateOnProcessOutput) {
                                $this->dispatch(RunEvent::UPDATED, new RunEvent($this));
                            }
                        }
                    }
                }
            );
            $this->completed = false;
        }

        return $this;
    }

    /**
     * Poll the process to see if it is still running, and trigger events
     *
     * @return bool true if the process is currently running (started and not terminated)
     */
    public function poll()
    {
        if ($this->completed || !$this->hasStarted()) {
            return false;
        }

        if ($this->process->isRunning()) {
            if ($this->updateOnPoll) {
                $this->dispatch(RunEvent::UPDATED, new RunEvent($this));
            }
            return true;
        }

        $this->completed = true;
        $this->setFinished();

        if ($this->process->isSuccessful()) {
            $this->successful = true;
            $this->dispatch(RunEvent::SUCCESSFUL, new RunEvent($this));
        } else {
            $this->dispatch(RunEvent::FAILED, new RunEvent($this));
        }
        $this->dispatch(RunEvent::COMPLETED, new RunEvent($this));

        return false;
    }

    /**
     * Return if the underlying process is running
     *
     * @return bool
     */
    public function isRunning()
    {
        return $this->process->isRunning();
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
        return $this->process->isStarted();
    }

    /**
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @param bool $updateOnPoll
     *
     * @return $this
     */
    public function setUpdateOnPoll($updateOnPoll)
    {
        $this->updateOnPoll = $updateOnPoll;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUpdateOnPoll()
    {
        return $this->updateOnPoll;
    }

    /**
     * @param bool $update
     *
     * @return $this
     */
    public function setUpdateOnProcessOutput($update)
    {
        $this->updateOnProcessOutput = $update;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUpdateOnProcessOutput()
    {
        return $this->updateOnProcessOutput;
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
        return $this->lastType;
    }

    /**
     * If the run was unsuccessful, get the error if applicable
     *
     * @return Exception[]|Throwable[]
     */
    public function getExceptions()
    {
        if ($this->hasStarted() && !$this->isSuccessful()) {
            return [new ProcessFailedException($this->process)];
        }
        return [];
    }
}
