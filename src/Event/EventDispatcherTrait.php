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

use Symfony\Contracts\EventDispatcher\Event;
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
    abstract protected function getEventNames();

    /**
     * @param string   $name
     * @param callable $handler
     *
     * @return $this
     */
    public function addListener($name, callable $handler)
    {
        $this->assertEventName($name);
        $this->getEventDispatcher()->addListener($name, $handler);
        return $this;
    }

    /**
     * @param string $name
     * @param Event  $event
     *
     * @return $this
     */
    protected function dispatch($name, Event $event)
    {
        $this->assertEventName($name);
        $this->getEventDispatcher()->dispatch($event, $name);
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
