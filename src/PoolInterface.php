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
use Symfony\Component\Process\Process;
use Traversable;

interface PoolInterface extends \Countable, DispatcherInterface
{
    const CHECK_INTERVAL = 0.1;

    /**
     * @param RunInterface|Process $item
     * @param array                $tags
     *
     * @return $this
     */
    public function add($item, array $tags = []);

    /**
     * Start this pool of runs
     *
     * @return $this
     */
    public function start();

    /**
     * Check to see if this pool has finished
     *
     * @return bool
     */
    public function poll();

    /**
     * Run this pool of runs and block until they are complete
     *
     * @param float $interval
     *
     * @return bool `true` if all the runs were successful
     */
    public function run($interval = self::CHECK_INTERVAL);

    /**
     * @return mixed[]
     */
    public function getAll();

    /**
     * @return RunInterface[]
     */
    public function getWaiting();

    /**
     * @return RunInterface[]
     */
    public function getRunning();

    /**
     * @return RunInterface[]
     */
    public function getFinished();
}
