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

trait RunningStateTrait
{
    /** @var float */
    protected $started = 0.0;
    /** @var float */
    protected $finished = 0.0;
    /** @var int */
    private $state = RunInterface::STATE_NOT_STARTED;

    /**
     * @return float
     */
    public function getDuration()
    {
        if ($this->finished > 0) {
            return $this->finished - $this->started;
        }
        return $this->started > 0 ? microtime(true) - $this->started : 0;
    }

    /**
     * Starts this thing
     *
     * @param int|null $time Optional time to say that we started at
     */
    protected function setStarted($time = null)
    {
        $this->started = $time ?: microtime(true);
        $this->setState(RunInterface::STATE_RUNNING);
    }

    /**
     * this thing has finished
     *
     * @param int|null $time Optional time to say that we finished at
     */
    protected function setFinished($time = null)
    {
        $this->finished = $time ?: microtime(true);
        $this->setState(RunInterface::STATE_NOT_RUNNING);
    }

    /**
     * @return int RunInterface::STATE_*
     */
    protected function getState()
    {
        return $this->state;
    }

    /**
     * @param int $state RunInterface::STATE_*
     *
     * @return $this
     */
    protected function setState($state)
    {
        $this->state = $state;
        return $this;
    }
}
