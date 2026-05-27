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

use Graze\ParallelProcess\PrioritisedInterface;
use Symfony\Contracts\EventDispatcher\Event;

class PriorityChangedEvent extends Event
{
    const CHANGED = 'priority.changed';

    /** @var PrioritisedInterface */
    private $item;
    /** @var float */
    private $priority;
    /** @var float|null */
    private $oldPriority;

    /**
     * RunEvent constructor.
     *
     * @param PrioritisedInterface $item
     * @param float                $priority
     * @param float|null           $oldPriority
     */
    public function __construct(PrioritisedInterface $item, $priority, $oldPriority = null)
    {
        $this->item = $item;
        $this->priority = $priority;
        $this->oldPriority = $oldPriority;
    }

    /**
     * @return PrioritisedInterface
     */
    public function getItem()
    {
        return $this->item;
    }
}
