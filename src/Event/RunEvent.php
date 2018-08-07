<?php

namespace Graze\ParallelProcess\Event;

use Graze\ParallelProcess\RunInterface;
use Symfony\Component\EventDispatcher\Event;

class RunEvent extends Event
{
    const STARTED   = 'started';
    const COMPLETED = 'completed';
    const FAILED    = 'failed';
    const UPDATED   = 'updated';

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
