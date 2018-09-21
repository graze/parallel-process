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

use Exception;
use Graze\ParallelProcess\Event\DispatcherInterface;
use Throwable;

interface RunInterface extends DispatcherInterface
{
    const STATE_NOT_STARTED = 0;
    const STATE_RUNNING     = 1;
    const STATE_NOT_RUNNING = 2;

    /**
     * Has this run been started before
     *
     * @return bool
     */
    public function hasStarted();

    /**
     * Start a run
     *
     * If it is currently running it will throw an exception
     *
     * @return $this
     *
     * @throws \Graze\ParallelProcess\Exceptions\NotRunningException
     */
    public function start();

    /**
     * Was this run successful
     *
     * @return bool
     */
    public function isSuccessful();

    /**
     * If the run was unsuccessful, get the error if applicable
     *
     * @return Exception[]|Throwable[]
     */
    public function getExceptions();

    /**
     * We think this is running
     *
     * @return bool
     */
    public function isRunning();

    /**
     * Pools to see if this process is running
     *
     * @return bool
     */
    public function poll();

    /**
     * Get a set of tags associated with this run
     *
     * @return array
     */
    public function getTags();

    /**
     * @return float number of seconds this run has been running for (0 for not started)
     */
    public function getDuration();

    /**
     * @return float[]|null an array of values of the current position, max, and percentage. null if not applicable
     */
    public function getProgress();

    /**
     * @return float The priority for this run, where the larger the number the higher the priority
     */
    public function getPriority();
}
