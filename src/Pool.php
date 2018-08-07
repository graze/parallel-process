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
use Graze\ParallelProcess\Event\EventDispatcherTrait;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\Exceptions\NotRunningException;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

class Pool extends Collection implements RunInterface
{
    use EventDispatcherTrait;

    const CHECK_INTERVAL = 0.1;
    const NO_MAX         = -1;

    /** @var RunInterface[] */
    protected $items = [];
    /** @var RunInterface[] */
    protected $running = [];
    /** @var RunInterface[] */
    protected $waiting = [];
    /** @var RunInterface[] */
    protected $finished = [];
    /** @var float */
    private $started;
    /** @var float */
    private $finishedTime;
    /** @var int */
    private $maxSimultaneous = -1;
    /** @var bool */
    private $runInstantly = false;
    /** @var string[] */
    private $tags;

    /**
     * Pool constructor.
     *
     * Set the default callbacks here
     *
     * @param RunInterface[]|Process[] $items
     * @param int                      $maxSimultaneous Maximum number of simulatneous processes
     * @param bool                     $runInstantly    Run any added processes immediately if they are not already
     *                                                  running
     * @param array                    $tags
     */
    public function __construct(
        array $items = [],
        $maxSimultaneous = self::NO_MAX,
        $runInstantly = false,
        array $tags = []
    ) {
        parent::__construct($items);

        $this->maxSimultaneous = $maxSimultaneous;
        $this->runInstantly = $runInstantly;

        if ($this->runInstantly) {
            $this->start();
        }
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
            PoolRunEvent::POOL_RUN_ADDED,
        ];
    }

    /**
     * Add a new process to the pool
     *
     * @param RunInterface|Process $item
     * @param array                $tags If a process is supplied, these are added to create a run.
     *                                   This is ignored when adding a run
     *
     * @return $this
     */
    public function add($item, array $tags = [])
    {
        if ($item instanceof Process) {
            return $this->addProcess($item, $tags);
        }

        if (!$item instanceof RunInterface) {
            throw new InvalidArgumentException("add: Can only add `RunInterface` to this collection");
        }

        if (!($this->isRunning() || $this->runInstantly) && $item->isRunning()) {
            throw new NotRunningException("add: unable to add a running item when the pool has not started");
        }

        parent::add($item);

        $this->dispatch(PoolRunEvent::POOL_RUN_ADDED, new PoolRunEvent($this, $item));

        if ($this->isRunning() || $this->runInstantly) {
            $this->startRun($item);
        }

        return $this;
    }

    /**
     * Add a new process to the pool using the default callbacks
     *
     * @param Process $process
     * @param array   $tags
     *
     * @return $this
     */
    private function addProcess(Process $process, array $tags = [])
    {
        return $this->add(new Run($process, $tags));
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

        if (count($this->running) > 0) {
            $this->started = microtime(true);
            $this->dispatch(RunEvent::STARTED, new RunEvent($this));
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
            if (is_null($this->started)) {
                $this->started = microtime(true);
                $this->dispatch(RunEvent::STARTED, new RunEvent($this));
            }
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

        $interval = (int) ($checkInterval * 1000000);
        while ($this->poll()) {
            $this->dispatch(RunEvent::UPDATED, new RunEvent($this));
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
        foreach ($this->running as $i => $run) {
            if (!$run->poll()) {
                $this->finished[] = $run;
                $this->running[$i] = null;
            }
        }
        $this->running = array_filter($this->running);

        $this->checkFinished();

        if (!$this->isRunning()) {
            $this->finishedTime = microtime(true);
            if ($this->isSuccessful()) {
                $this->dispatch(RunEvent::COMPLETED, new RunEvent($this));
            } else {
                $this->dispatch(RunEvent::FAILED, new RunEvent($this));
            }
            return false;
        }

        return true;
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
     * Get a list of all the current waiting runs
     *
     * @return RunInterface[]
     */
    public function getFinished()
    {
        return $this->finished;
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

    /**
     * @return bool
     */
    public function isRunInstantly()
    {
        return $this->runInstantly;
    }

    /**
     * @param bool $runInstantly
     *
     * @return Pool
     */
    public function setRunInstantly($runInstantly)
    {
        $this->runInstantly = $runInstantly;
        return $this;
    }

    /**
     * Get a set of tags associated with this run
     *
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @return float number of seconds this run has been running or did run for (0 for not started)
     */
    public function getDuration()
    {
        if ($this->isRunning()) {
            return microtime(true) - $this->started;
        } elseif (!$this->hasStarted()) {
            return 0;
        }
        return $this->finishedTime - $this->started;
    }

    /**
     * @return float[]|null an array of values of the current position, max, and percentage. null if not applicable
     */
    public function getProgress()
    {
        return [count($this->finished), count($this->items), count($this->finished) / count($this->items)];
    }
}
