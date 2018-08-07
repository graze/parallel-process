<?php

namespace Graze\ParallelProcess;

use Graze\ParallelProcess\Event\EventDispatcherTrait;
use Graze\ParallelProcess\Event\RunEvent;
use Symfony\Component\Process\Process;

class Run implements RunInterface
{
    use EventDispatcherTrait;

    const ON_SUCCESS  = 1;
    const ON_FAILURE  = 2;
    const ON_PROGRESS = 3;

    /** @var Process */
    private $process;
    /** @var float */
    private $started = 0;
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
    /** @var array */
    private $tags;

    /**
     * Run constructor.
     *
     * @param Process $process
     * @param array   $tags List of key value tags associated with this run
     */
    public function __construct(Process $process, array $tags = [])
    {
        $this->process = $process;
        $this->tags = $tags;
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
        if (!$this->process->isRunning()) {
            $this->started = microtime(true);
            $this->dispatch(RunEvent::STARTED, new RunEvent($this));
            $this->process->start(
                function ($type, $data) {
                    $this->lastType = $type;
                    foreach (explode("\n", $data) as $line) {
                        $this->last = rtrim($line);
                        if (mb_strlen($this->last) > 0 && $this->updateOnProcessOutput) {
                            $this->dispatch(RunEvent::UPDATED, new RunEvent($this));
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

        if ($this->process->isSuccessful()) {
            $this->successful = true;
            $this->dispatch(RunEvent::COMPLETED, new RunEvent($this));
        } else {
            $this->dispatch(RunEvent::FAILED, new RunEvent($this));
        }
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
     * @return float number of seconds this run has been running for (0 for not started)
     */
    public function getDuration()
    {
        return $this->started > 0 ? microtime(true) - $this->started : 0;
    }

    /**
     * @return float|null the process between 0 and 1 if the run supports it, otherwise null
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
}
