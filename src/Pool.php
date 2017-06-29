<?php
/**
 * This file is part of graze/parallel-process.
 *
 * Copyright (c) 2017 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

namespace Graze\ParallelProcess;

use Graze\DataStructure\Collection\Collection;
use Graze\ParallelProcess\Exceptions\NotRunningException;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

class Pool extends Collection implements RunInterface
{
    const CHECK_INTERVAL = 0.1;
    const NO_MAX         = -1;

    /** @var RunInterface[] */
    protected $items = [];
    /** @var RunInterface[] */
    protected $running = [];
    /** @var RunInterface[] */
    protected $waiting = [];
    /** @var callable|null */
    protected $onSuccess;
    /** @var callable|null */
    protected $onFailure;
    /** @var callable|null */
    protected $onProgress;
    /** @var int */
    private $maxSimultaneous = -1;

    /**
     * Pool constructor.
     *
     * Set the default callbacks here
     *
     * @param RunInterface[]|Process[] $items
     * @param callable|null            $onSuccess  function (Process $process, float $duration, string $last) : void
     * @param callable|null            $onFailure  function (Process $process, float $duration, string $last) : void
     * @param callable|null            $onProgress function (Process $process, float $duration, string $last) : void
     * @param int                      $maxSimultaneous
     */
    public function __construct(
        array $items = [],
        callable $onSuccess = null,
        callable $onFailure = null,
        callable $onProgress = null,
        $maxSimultaneous = self::NO_MAX
    ) {
        parent::__construct($items);

        $this->onSuccess = $onSuccess;
        $this->onFailure = $onFailure;
        $this->onProgress = $onProgress;
        $this->maxSimultaneous = $maxSimultaneous;
    }

    /**
     * @param callable|null $onSuccess function (Process $process, float $duration, string $last) : void
     *
     * @return $this
     */
    public function setOnSuccess($onSuccess)
    {
        $this->onSuccess = $onSuccess;
        return $this;
    }

    /**
     * @param callable|null $onFailure function (Process $process, float $duration, string $last) : void
     *
     * @return $this
     */
    public function setOnFailure($onFailure)
    {
        $this->onFailure = $onFailure;
        return $this;
    }

    /**
     * @param callable|null $onProgress function (Process $process, float $duration, string $last) : void
     *
     * @return $this
     */
    public function setOnProgress($onProgress)
    {
        $this->onProgress = $onProgress;
        return $this;
    }

    /**
     * Add a new process to the pool
     *
     * @param RunInterface|Process $item
     *
     * @return $this
     */
    public function add($item)
    {
        if ($item instanceof Process) {
            return $this->addProcess($item);
        }

        if (!$item instanceof RunInterface) {
            throw new InvalidArgumentException("add: Can only add `RunInterface` to this collection");
        }

        if (!$this->isRunning() && $item->isRunning()) {
            throw new NotRunningException("add: unable to add a running item when the pool has not started");
        }

        parent::add($item);

        if ($this->isRunning()) {
            $this->startRun($item);
        }

        return $this;
    }

    /**
     * Add a new process to the pool using the default callbacks
     *
     * @param Process $process
     *
     * @return $this
     */
    protected function addProcess(Process $process)
    {
        return $this->add(new Run(
            $process,
            $this->onSuccess,
            $this->onFailure,
            $this->onProgress
        ));
    }

    /**
     * Start all the processes running
     *
     * @return $this
     */
    public function start()
    {
        foreach ($this->items as $run) {
            $this->startRun($run);
        }

        return $this;
    }

    /**
     * Start a run (or queue it if we are running the maximum number of processes already)
     *
     * @param RunInterface $run
     */
    private function startRun(RunInterface $run)
    {
        if ($this->maxSimultaneous === static::NO_MAX || count($this->running) < $this->maxSimultaneous) {
            $run->start();
            $this->running[] = $run;
        } else {
            $this->waiting[] = $run;
        }
    }

    /**
     * Blocking call to run processes;
     *
     * @param float $checkInterval Seconds between checks
     *
     * @return bool true if all processes were successful
     */
    public function run($checkInterval = self::CHECK_INTERVAL)
    {
        $this->start();
        $interval = $checkInterval * 1000000;

        while ($this->poll()) {
            usleep($interval);
        }

        return $this->isSuccessful();
    }

    /**
     * Check when a run has finished, if there are processes waiting, start them
     */
    private function checkFinished()
    {
        if ($this->maxSimultaneous !== static::NO_MAX
            && count($this->waiting) > 0
            && count($this->running) < $this->maxSimultaneous) {
            for ($i = count($this->running); $i < $this->maxSimultaneous && count($this->waiting) > 0; $i++) {
                $run = array_shift($this->waiting);
                $run->start();
                $this->running[] = $run;
            }
        }
    }

    /**
     * Determine if any item has run
     *
     * @return bool
     */
    public function hasStarted()
    {
        foreach ($this->items as $run) {
            if ($run->hasStarted()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Are any of the processes running
     *
     * @return bool
     */
    public function poll()
    {
        /** @var Run[] $running */
        $this->running = array_filter($this->running, function (RunInterface $run) {
            return $run->poll();
        });

        $this->checkFinished();

        return $this->isRunning();
    }

    /**
     * @return bool
     */
    public function isRunning()
    {
        return count($this->running) > 0;
    }

    /**
     * Return if all runs have started and were successful
     *
     * @return bool
     */
    public function isSuccessful()
    {
        if (!$this->hasStarted()) {
            return false;
        }

        foreach ($this->items as $run) {
            if (!$run->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a list of all the currently running runs
     *
     * @return RunInterface[]
     */
    public function getRunning()
    {
        return $this->running;
    }

    /**
     * Get a list of all the current waiting runs
     *
     * @return RunInterface[]
     */
    public function getWaiting()
    {
        return $this->waiting;
    }

    /**
     * @return int
     */
    public function getMaxSimultaneous()
    {
        return $this->maxSimultaneous;
    }

    /**
     * @param int $maxSimultaneous
     *
     * @return $this
     */
    public function setMaxSimultaneous($maxSimultaneous)
    {
        $this->maxSimultaneous = $maxSimultaneous;
        return $this;
    }
}
