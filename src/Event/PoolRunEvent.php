<?php

namespace Graze\ParallelProcess\Event;

use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\RunInterface;

class PoolRunEvent extends RunEvent
{
    const POOL_RUN_ADDED = 'run.added';

    /** @var Pool */
    private $pool;

    /**
     * PoolRunEvent constructor.
     *
     * @param Pool         $pool
     * @param RunInterface $run
     */
    public function __construct(Pool $pool, RunInterface $run)
    {
        parent::__construct($run);
        $this->pool = $pool;
    }

    /**
     * @return Pool
     */
    public function getPool()
    {
        return $this->pool;
    }
}
