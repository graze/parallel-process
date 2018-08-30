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

namespace Graze\ParallelProcess\Event;

use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\RunInterface;

class PoolRunEvent extends RunEvent
{
    const POOL_RUN_ADDED = 'run.added';

    /** @var PoolInterface */
    private $pool;

    /**
     * PoolRunEvent constructor.
     *
     * @param PoolInterface $pool
     * @param RunInterface  $run
     */
    public function __construct(PoolInterface $pool, RunInterface $run)
    {
        parent::__construct($run);
        $this->pool = $pool;
    }

    /**
     * @return PoolInterface
     */
    public function getPool()
    {
        return $this->pool;
    }
}
