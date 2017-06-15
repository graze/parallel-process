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
use Graze\ParallelProcess\Exceptions\AlreadyRunningException;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

class Pool extends Collection implements RunInterface
{
    const CHECK_INTERVAL = 0.1;

    /** @var RunInterface[] */
    protected $items = [];
    /** @var RunInterface[] */
    protected $running = [];
    /** @var callable|null */
    protected $onSuccess;
    /** @var callable|null */
    protected $onFailure;
    /** @var callable|null */
    protected $onProgress;

    /**
     * Pool constructor.
     *
     * Set the default callbacks here
     *
     * @param RunInterface[]|Process[] $items
     * @param callable|null            $onSuccess  function (Process $process, float $duration, string $last) : void
     * @param callable|null            $onFailure  function (Process $process, float $duration, string $last) : void
     * @param callable|null            $onProgress function (Process $process, float $duration, string $last) : void
     */
    public function __construct(
        array $items = [],
        callable $onSuccess = null,
        callable $onFailure = null,
        callable $onProgress = null
    ) {
        parent::__construct($items);

        $this->onSuccess = $onSuccess;
        $this->onFailure = $onFailure;
        $this->onProgress = $onProgress;
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

        $itemRunning = $item->isRunning();
        if ((count($this->running) > 0) && !$itemRunning) {
            throw new AlreadyRunningException("add: unable to add an item when the pool is currently running");
        }

        parent::add($item);

        if ($itemRunning) {
            $this->running[] = $item;
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
            $run->start();
        }

        $this->running = $this->items;

        return $this;
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

        while ($this->isRunning()) {
            usleep($checkInterval * 1000000);
        }

        return $this->isSuccessful();
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
    public function isRunning()
    {
        /** @var Run[] $running */
        $this->running = array_filter($this->running, function (RunInterface $run) {
            return $run->isRunning();
        });

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
}
