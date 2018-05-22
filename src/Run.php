<?php

namespace Graze\ParallelProcess;

use Symfony\Component\Process\Process;

class Run implements RunInterface
{
    const ON_SUCCESS  = 1;
    const ON_FAILURE  = 2;
    const ON_PROGRESS = 3;

    /** @var Process */
    private $process;
    /** @var callable|null */
    private $onSuccess;
    /** @var callable|null */
    private $onFailure;
    /** @var callable|null */
    private $onProgress;
    /** @var callable|null */
    private $onStart;
    /** @var float */
    private $started;
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

    /**
     * Run constructor.
     *
     * @param Process       $process
     * @param callable|null $onSuccess  When the process finishes and is successful
     *                                  function (Process $process, float $duration, string $last, string $lastType) :
     *                                  void
     * @param callable|null $onFailure  When the process finishes and failed
     *                                  function (Process $process, float $duration, string $last, string $lastType) :
     *                                  void
     * @param callable|null $onProgress Called every check period or a message is returned from the process
     *                                  function (Process $process, float $duration, string $last, string $lastType) :
     *                                  void
     * @param callable|null $onStart    When the process starts
     *                                  function (Process $process, float $duration, string $last, string $lastType) :
     *                                  void
     */
    public function __construct(
        Process $process,
        callable $onSuccess = null,
        callable $onFailure = null,
        callable $onProgress = null,
        callable $onStart = null
    ) {
        $this->process = $process;
        $this->onSuccess = $onSuccess;
        $this->onFailure = $onFailure;
        $this->onProgress = $onProgress;
        $this->onStart = $onStart;
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
            $this->update($this->onStart);
            $this->process->start(
                function ($type, $data) {
                    $this->lastType = $type;
                    foreach (explode("\n", $data) as $line) {
                        $this->last = rtrim($line);
                        if ($this->updateOnProcessOutput) {
                            $this->update($this->onProgress);
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
                $this->update($this->onProgress);
            }
            return true;
        }

        $this->completed = true;

        if ($this->process->isSuccessful()) {
            $this->successful = true;
            $this->update($this->onSuccess);
        } else {
            $this->update($this->onFailure);
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
     * Call an event callback
     *
     * @param callable|null $func
     */
    protected function update($func)
    {
        if (!is_null($func)) {
            call_user_func(
                $func,
                $this->process,
                microtime(true) - $this->started,
                $this->last,
                $this->lastType
            );
        }
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
}
