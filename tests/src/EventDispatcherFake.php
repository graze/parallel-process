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
