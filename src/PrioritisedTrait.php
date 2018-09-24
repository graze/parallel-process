<?php

namespace Graze\ParallelProcess;

use Graze\ParallelProcess\Event\DispatcherInterface;
use Graze\ParallelProcess\Event\PriorityChangedEvent;

trait PrioritisedTrait
{
    /** @var float */
    protected $priority;

    /**
     * @param float $priority
     *
     * @return $this
     */
    public function setPriority($priority)
    {
        $oldPriority = $this->priority;
        $this->priority = $priority;
        if (!($this instanceof RunInterface && $this->hasStarted())
            && method_exists($this, 'dispatch')
            && $this instanceof PrioritisedInterface) {
            $this->dispatch(PriorityChangedEvent::CHANGED, new PriorityChangedEvent($this, $priority, $oldPriority));
        }
        return $this;
    }

    /**
     * @return float
     */
    public function getPriority()
    {
        return $this->priority;
    }
}
