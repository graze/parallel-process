<?php

namespace Graze\ParallelProcess\Event;

interface DispatcherInterface
{
    /**
     * @param string   $name
     * @param callable $handler
     *
     * @return $this
     */
    public function addListener($name, callable $handler);
}
