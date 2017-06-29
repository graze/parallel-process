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
    /** @var float */
    private $started;
    /** @var bool */
    private $successful = false;
    /** @var bool */
    private $completed = false;
    /** @var string */
    private $last = '';

    /**
     * Run constructor.
     *
     * @param Process       $process
     * @param callable|null $onSuccess  function (Process $process, float $duration, string $last) : void
     * @param callable|null $onFailure  function (Process $process, float $duration, string $last) : void
     * @param callable|null $onProgress function (Process $process, float $duration, string $last) : void
     */
    public function __construct(
        Process $process,
        callable $onSuccess = null,
        callable $onFailure = null,
        callable $onProgress = null
    ) {
        $this->process = $process;
        $this->onSuccess = $onSuccess;
        $this->onFailure = $onFailure;
        $this->onProgress = $onProgress;
    }

    /**
     * Start the process
     *
     * @return $this
     */
    public function start()
    {
        if (!$this->process->isRunning()) {
            $this->process->start(function ($type, $data) {
                $this->last = rtrim($data);
            });
            $this->started = microtime(true);
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
            $this->update($this->onProgress);
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
                $this->last
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
}
