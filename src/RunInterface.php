<?php

namespace Graze\ParallelProcess;

interface RunInterface
{
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
}
