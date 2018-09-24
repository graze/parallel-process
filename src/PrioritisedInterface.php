<?php

namespace Graze\ParallelProcess;

interface PrioritisedInterface
{
    /**
     * Get the priority for this item. The higher the number the higher the priority
     *
     * @return float
     */
    public function getPriority();

    /**
     * Fluent call for setting the priority.
     *
     * @param float $priority The higher the number the higher the priority
     *
     * @return $this
     */
    public function setPriority($priority);
}
