<?php

namespace Graze\ParallelProcess\Test;

use Graze\ParallelProcess\Event\EventDispatcherTrait;
use Symfony\Component\EventDispatcher\Event;

class EventDispatcherFake
{
    const EVENT_VALID   = 'valid';
    const EVENT_INVALID = 'invalid';

    use EventDispatcherTrait;

    /**
     * @return string[]
     */
    protected function getEventNames()
    {
        return [static::EVENT_VALID];
    }

    /**
     * @param string $eventName
     * @param Event  $event
     */
    public function doDispatch($eventName, Event $event)
    {
        $this->dispatch($eventName, $event);
    }
}
