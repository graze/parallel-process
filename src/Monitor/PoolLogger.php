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

namespace Graze\ParallelProcess\Monitor;

use Exception;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\RunInterface;
use Psr\Log\LoggerInterface;

class PoolLogger
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * LoggingMonitor constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Monitor a Pool or Run, and log all activity
     *
     * @param PoolInterface|RunInterface $item
     */
    public function monitor($item)
    {
        if ($item instanceof PoolInterface) {
            $item->addListener(PoolRunEvent::POOL_RUN_ADDED, [$this, 'onPoolRunAdded']);
            $item->addListener(PoolRunEvent::UPDATED, [$this, 'onPoolUpdated']);
            array_map([$this, 'monitor'], $item->getAll());
        }
        if ($item instanceof RunInterface) {
            $item->addListener(RunEvent::STARTED, [$this, 'onRunStarted']);
            $item->addListener(RunEvent::SUCCESSFUL, [$this, 'onRunSuccessful']);
            $item->addListener(RunEvent::FAILED, [$this, 'onRunFailed']);
            $item->addListener(RunEvent::COMPLETED, [$this, 'onRunCompleted']);
        }
    }

    /**
     * @param PoolRunEvent $event
     */
    public function onPoolRunAdded(PoolRunEvent $event)
    {
        $this->logger->debug(
            sprintf(
                'pool [%s:%s]: Run [%s:%s] has been added',
                get_class($event->getPool()),
                spl_object_hash($event->getPool()),
                get_class($event->getRun()),
                spl_object_hash($event->getRun())
            ),
            array_merge($this->getTags($event->getPool()), $this->getTags($event->getRun()))
        );
        $this->monitor($event->getRun());
    }

    /**
     * @param RunEvent $event
     */
    public function onPoolUpdated(RunEvent $event)
    {
        $pool = $event->getRun();
        if ($pool instanceof PoolInterface) {
            $this->logger->debug(
                sprintf('pool [%s:%s]: updated', get_class($event->getRun()), spl_object_hash($pool)),
                $this->getTags($pool)
            );
        }
    }

    /**
     * @param RunEvent $event
     */
    public function onRunStarted(RunEvent $event)
    {
        $this->logger->debug(
            sprintf('run [%s:%s]: has started', get_class($event->getRun()), spl_object_hash($event->getRun())),
            $this->getTags($event->getRun())
        );
    }

    /**
     * @param RunEvent $event
     */
    public function onRunSuccessful(RunEvent $event)
    {
        $this->logger->debug(
            sprintf(
                'run [%s:%s]: successfully finished',
                get_class($event->getRun()),
                spl_object_hash($event->getRun())
            ),
            $this->getTags($event->getRun())
        );
    }

    /**
     * @param RunEvent $event
     */
    public function onRunFailed(RunEvent $event)
    {
        $errors = array_map(
            function (Exception $e) {
                return $e->getMessage();
            },
            $event->getRun()->getExceptions()
        );
        $this->logger->debug(
            sprintf(
                'run [%s:%s]: failed - %s',
                get_class($event->getRun()),
                spl_object_hash($event->getRun()),
                count($errors) > 0 ? reset($errors) : ''
            ),
            array_merge(['errors' => $errors], $this->getTags($event->getRun()))
        );
    }

    /**
     * @param RunEvent $event
     */
    public function onRunCompleted(RunEvent $event)
    {
        $this->logger->debug(
            sprintf(
                'run [%s:%s]: has finished running',
                get_class($event->getRun()),
                spl_object_hash($event->getRun())
            ),
            $this->getTags($event->getRun())
        );
    }

    /**
     * @param PoolInterface|RunInterface $item
     *
     * @return array
     */
    private function getTags($item)
    {
        if ($item instanceof PoolInterface) {
            return $this->getPoolTags($item);
        }
        return $this->getRunTags($item);
    }

    /**
     * @param PoolInterface $pool
     *
     * @return array
     */
    private function getPoolTags(PoolInterface $pool)
    {
        $tags = [];
        if ($pool instanceof RunInterface) {
            $tags = $this->getRunTags($pool);
        }
        return [
            'pool' => array_merge(
                [
                    'type'         => get_class($pool),
                    'id'           => spl_object_hash($pool),
                    'num_waiting'  => count($pool->getWaiting()),
                    'num_running'  => count($pool->getRunning()),
                    'num_finished' => count($pool->getFinished()),
                ],
                (isset($tags['run']) ? $tags['run'] : [])
            ),
        ];
    }

    /**
     * @param RunInterface $run
     *
     * @return array
     */
    private function getRunTags(RunInterface $run)
    {
        return [
            'run' => [
                'type'         => get_class($run),
                'id'           => spl_object_hash($run),
                'tags'         => $run->getTags(),
                'hasStarted'   => $run->hasStarted(),
                'isRunning'    => $run->isRunning(),
                'isSuccessful' => $run->isSuccessful(),
                'duration'     => $run->getDuration(),
                'priority'     => $run->getPriority(),
                'progress'     => $run->getProgress() ? $run->getProgress()[0] : null,
            ],
        ];
    }
}
