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

use Graze\ParallelProcess\Event\DispatcherInterface;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\PriorityChangedEvent;
use Graze\ParallelProcess\Exceptions\NotRunningException;
use SplPriorityQueue;
use Symfony\Component\Process\Process;

/**
 * A PriorityPool allows you to manage how many, and the order on which to run child runs
 */
class PriorityPool extends Pool
{
    const NO_MAX = -1;

    /** @var SplPriorityQueue */
    protected $waitingQueue;
    /** @var int */
    private $maxSimultaneous = -1;
    /** @var bool */
    private $runInstantly = false;
    /** @var bool */
    private $initialised = false;

    /**
     * Pool constructor.
     *
     * @param RunInterface[]|Process[] $items
     * @param int                      $maxSimultaneous Maximum number of simultaneous processes
     * @param bool                     $runInstantly    Run any added processes immediately if they are not already
     *                                                  running
     * @param array                    $tags
     * @param float                    $priority
     */
    public function __construct(
        array $items = [],
        $maxSimultaneous = self::NO_MAX,
        $runInstantly = false,
        array $tags = [],
        $priority = 1.0
    ) {
        $this->maxSimultaneous = $maxSimultaneous;
        $this->runInstantly = $runInstantly;
        $this->waitingQueue = new SplPriorityQueue();
        $this->waitingQueue->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        parent::__construct($items, $tags, $priority);

        $this->initialised = true;
        if ($this->isRunning() || $runInstantly) {
            $this->startNext();
        }
    }

    /**
     * Add a new process to the pool
     *
     * @param RunInterface|PoolInterface|Process $item
     * @param array                              $tags If a process is supplied, these are added to create a run.
     *                                                 This is ignored when adding a run
     *
     * @return $this
     */
    public function add($item, array $tags = [])
    {
        if ($item instanceof RunInterface
            && !($this->isRunning()
                 || $this->runInstantly)
            && $item->isRunning()) {
            throw new NotRunningException("add: unable to add a running item when the pool has not started");
        }

        // special handling of child collections, a pool should only care about leaf nodes
        if ($item instanceof PoolInterface) {
            $item->addListener(
                PoolRunEvent::POOL_RUN_ADDED,
                function (PoolRunEvent $event) {
                    $this->add($event->getRun());
                }
            );
            foreach ($item->getAll() as $child) {
                $this->add($child);
            }
            return $this;
        }

        parent::add($item, $tags);

        if ($item instanceof RunInterface && !$item->hasStarted()) {
            $this->waitingQueue->insert($item, $item->getPriority());
            if ($this->runInstantly) {
                $this->startNext();
            }
        }

        if ($item instanceof PrioritisedInterface && $item instanceof DispatcherInterface) {
            $item->addListener(PriorityChangedEvent::CHANGED, [$this, 'onPriorityChanged']);
        }

        return $this;
    }

    /**
     * @param PriorityChangedEvent $event
     */
    public function onPriorityChanged(PriorityChangedEvent $event)
    {
        $index = array_search($event->getItem(), $this->waiting, true);
        if ($index !== false) {
            // we are unable to delete an item from a SplPriorityQueue, so we delete it and start again here
            $this->waitingQueue = new SplPriorityQueue();
            foreach ($this->waiting as $item) {
                $this->waitingQueue->insert($item, $item->getPriority());
            }
        }
    }

    /**
     * Start all the processes running
     *
     * @return $this
     */
    public function start()
    {
        $this->startNext();

        return $this;
    }

    /**
     * Blocking call to run processes;
     *
     * @param float $checkInterval Seconds between checks
     *
     * @return bool `true` if all the runs were successful
     */
    public function run($checkInterval = self::CHECK_INTERVAL)
    {
        $this->startNext();

        $interval = (int) ($checkInterval * 1000000);
        while ($this->poll()) {
            usleep($interval);
        }

        return $this->isSuccessful();
    }

    /**
     * @return bool
     */
    public function poll()
    {
        parent::poll();
        $this->startNext();
        return $this->isRunning();
    }

    /**
     * Actually start a run
     *
     * @param RunInterface $run
     */
    private function startRun(RunInterface $run)
    {
        $run->start();
    }

    /**
     * Check when a run has finished, if there are processes waiting, start them
     */
    private function startNext()
    {
        // this allows us to wait until all runs are added in the constructor before running any.
        // Thus preserving the priority they have supplied.
        if (!$this->initialised) {
            return;
        }

        if ($this->maxSimultaneous !== static::NO_MAX
            && $this->waitingQueue->valid()
            && count($this->running) < $this->maxSimultaneous) {
            for ($i = count($this->running); $i < $this->maxSimultaneous && $this->waitingQueue->valid(); $i++) {
                $this->startRun($this->waitingQueue->extract());
            }
        } elseif ($this->maxSimultaneous === static::NO_MAX) {
            while ($this->waitingQueue->valid()) {
                $this->startRun($this->waitingQueue->extract());
            }
        }
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
     * @return PriorityPool
     */
    public function setRunInstantly($runInstantly)
    {
        $this->runInstantly = $runInstantly;
        return $this;
    }
}
