<?php

namespace Graze\ParallelProcess;

use Exception;
use Graze\DataStructure\Collection\Collection;
use Graze\ParallelProcess\Event\EventDispatcherTrait;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\RunEvent;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Class Pool
 *
 * A Pool is a arbitrary collection of runs that can be used to group runs together when displaying with a
 * Table
 *
 * @package Graze\ParallelProcess
 */
class Pool extends Collection implements RunInterface, PoolInterface
{
    use EventDispatcherTrait;
    use RunningStateTrait;

    /** @var RunInterface[] */
    protected $items = [];
    /** @var RunInterface[] */
    protected $waiting = [];
    /** @var RunInterface[] */
    protected $running = [];
    /** @var RunInterface[] */
    protected $complete = [];
    /** @var Exception[]|Throwable[] */
    private $exceptions = [];
    /** @var array */
    private $tags;
    /** @var float */
    private $priority;

    /**
     * RunCollection constructor.
     *
     * @param RunInterface[] $runs
     * @param array          $tags
     * @param float          $priority
     */
    public function __construct(array $runs = [], array $tags = [], $priority = 1.0)
    {
        parent::__construct([]);

        $this->tags = $tags;

        array_map([$this, 'add'], $runs);
        $this->priority = $priority;
    }

    /**
     * @param RunInterface|Process $item
     * @param array                $tags
     *
     * @return $this
     */
    public function add($item, array $tags = [])
    {
        if ($item instanceof Process) {
            return $this->add(new ProcessRun($item, $tags));
        }
        if (!$item instanceof RunInterface) {
            throw new InvalidArgumentException('item must implement `RunInterface`');
        }

        parent::add($item);
        $status = 'waiting';
        if ($item->isRunning()) {
            $status = 'running';
            $this->running[] = $item;
        } elseif ($item->hasStarted()) {
            $status = 'finished';
            $this->finished[] = $item;
        } else {
            $this->waiting[] = $item;
        }

        $item->addListener(RunEvent::STARTED, [$this, 'onRunStarted']);
        $item->addListener(RunEvent::COMPLETED, [$this, 'onRunCompleted']);
        $item->addListener(RunEvent::FAILED, [$this, 'onRunFailed']);

        $this->dispatch(PoolRunEvent::POOL_RUN_ADDED, new PoolRunEvent($this, $item));

        if ($status == 'running' || $status == 'finished') {
            if ($this->state == static::STATE_NOT_STARTED) {
                $this->setStarted();
                $this->dispatch(RunEvent::STARTED, new RunEvent($this));
            }
        }

        return $this;
    }

    /**
     * When a run starts, check our current state and start ourselves in required
     *
     * @param RunEvent $event
     */
    public function onRunStarted(RunEvent $event)
    {
        $index = array_search($event->getRun(), $this->waiting, true);
        if ($index !== false) {
            unset($this->waiting[$index]);
        }
        $this->running[] = $event->getRun();
        if ($this->state == static::STATE_NOT_STARTED) {
            $this->setStarted();
            $this->dispatch(RunEvent::STARTED, new RunEvent($this));
        }
        $this->dispatch(RunEvent::UPDATED, new RunEvent($this));
    }

    /**
     * When a run is completed, check if everything has finished
     *
     * @param RunEvent $event
     */
    public function onRunCompleted(RunEvent $event)
    {
        $index = array_search($event->getRun(), $this->running, true);
        if ($index !== false) {
            unset($this->running[$index]);
        }
        $this->complete[] = $event->getRun();
        $this->dispatch(RunEvent::UPDATED, new RunEvent($this));
        if (count($this->waiting) === 0 && count($this->running) === 0) {
            $this->setFinished();
            if ($this->isSuccessful()) {
                $this->dispatch(RunEvent::SUCCESSFUL, new RunEvent($this));
            } else {
                $this->dispatch(RunEvent::FAILED, new RunEvent($this));
            }
            $this->dispatch(RunEvent::COMPLETED, new RunEvent($this));
        }
    }

    /**
     * Handle any errors returned from the child run
     *
     * @param RunEvent $event
     */
    public function onRunFailed(RunEvent $event)
    {
        $this->exceptions = array_merge($this->exceptions, $event->getRun()->getExceptions());
    }

    /**
     * Has this run been started before
     *
     * @return bool
     */
    public function hasStarted()
    {
        return $this->getState() !== static::STATE_NOT_STARTED;
    }

    /**
     * Start all non running children
     *
     * @return $this
     *
     * @throws \Graze\ParallelProcess\Exceptions\NotRunningException
     */
    public function start()
    {
        foreach ($this->items as $run) {
            if (!$run->hasStarted()) {
                $run->start();
            }
        }
        return $this;
    }

    /**
     * Was this run successful
     *
     * @return bool
     */
    public function isSuccessful()
    {
        if ($this->getState() === static::STATE_NOT_RUNNING) {
            foreach ($this->items as $run) {
                if (!$run->isSuccessful()) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * If the run was unsuccessful, get the error if applicable
     *
     * @return Exception[]|Throwable[]
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * We think this is running
     *
     * @return bool
     */
    public function isRunning()
    {
        return $this->getState() === static::STATE_RUNNING;
    }

    /**
     * Pools to see if this process is running
     *
     * @return bool
     */
    public function poll()
    {
        foreach ($this->running as $run) {
            $run->poll();
        }
        return $this->isRunning();
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
     * @return float[]|null an array of values of the current position, max, and percentage. null if not applicable
     */
    public function getProgress()
    {
        return [count($this->complete), count($this->items), count($this->complete) / count($this->items)];
    }

    /**
     * @return float
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param float $priority
     *
     * @return $this
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
            RunEvent::SUCCESSFUL,
            RunEvent::FAILED,
            RunEvent::UPDATED,
            PoolRunEvent::POOL_RUN_ADDED,
        ];
    }

    /**
     * Run this pool of runs and block until they are complete.
     *
     * Note this will run the parent pool
     *
     * @param float $interval
     *
     * @return bool `true` if all the runs were successful
     */
    public function run($interval = self::CHECK_INTERVAL)
    {
        $this->start();

        $sleep = (int) ($interval * 1000000);
        while ($this->poll()) {
            usleep($sleep);
        }

        return $this->isSuccessful();
    }

    /**
     * @return RunInterface[]
     */
    public function getWaiting()
    {
        return array_values($this->waiting);
    }

    /**
     * @return RunInterface[]
     */
    public function getRunning()
    {
        return array_values($this->running);
    }

    /**
     * @return RunInterface[]
     */
    public function getFinished()
    {
        return array_values($this->complete);
    }
}
