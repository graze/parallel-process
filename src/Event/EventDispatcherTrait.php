<?php

namespace Graze\ParallelProcess\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait EventDispatcherTrait
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /**
     * @return EventDispatcherInterface
     */
    protected function getEventDispatcher()
    {
        if (is_null($this->eventDispatcher)) {
            $this->eventDispatcher = new EventDispatcher();
        }
        return $this->eventDispatcher;
    }

    /**
     * @return string[]
     */
    protected abstract function getEventNames();

    /**
     * @param string   $eventName
     * @param callable $handler
     *
     * @return $this
     */
    public function addListener($eventName, callable $handler)
    {
        $this->assertEventName($eventName);
        $this->getEventDispatcher()->addListener($eventName, $handler);
        return $this;
    }

    /**
     * @param string $eventName
     * @param Event  $event
     *
     * @return $this
     */
    protected function dispatch($eventName, Event $event)
    {
        $this->assertEventName($eventName);
        $this->getEventDispatcher()->dispatch($eventName, $event);
        return $this;
    }

    /**
     * @param string $name
     *
     * @throws \InvalidArgumentException
     */
    private function assertEventName($name)
    {
        if (!in_array($name, $this->getEventNames())) {
            throw new \InvalidArgumentException(sprintf(
                'The supplied event name: %s is not one of the expected: %s',
                $name,
                implode(', ', $this->getEventNames())
            ));
        }
    }
}
