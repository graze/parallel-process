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

use Graze\ParallelProcess\RunInterface;
use Symfony\Contracts\EventDispatcher\Event;

class RunEvent extends Event
{
    const STARTED    = 'started';
    const COMPLETED  = 'completed';
    const FAILED     = 'failed';
    const UPDATED    = 'updated';
    const SUCCESSFUL = 'successful';

    /** @var RunInterface */
    private $run;

    /**
     * RunEvent constructor.
     *
     * @param RunInterface $run
     */
    public function __construct(RunInterface $run)
    {
        $this->run = $run;
    }

    /**
     * @return RunInterface
     */
    public function getRun()
    {
        return $this->run;
    }
}
