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

namespace Graze\ParallelProcess\Test\Unit\Event;

use Graze\ParallelProcess\Test\EventDispatcherFake;
use Graze\ParallelProcess\Test\TestCase;
use Symfony\Component\EventDispatcher\Event;

class EventDispatcherTest extends TestCase
{
    /** @var EventDispatcherFake */
    private $dispatcher;

    public function setUp()
    {
        $this->dispatcher = new EventDispatcherFake();
    }

    public function testValidNameAddListener()
    {
        $this->assertSame(
            $this->dispatcher,
            $this->dispatcher->addListener(
                EventDispatcherFake::EVENT_VALID,
                function () {
                }
            )
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidNameAddListenerThrowsAnException()
    {
        $this->dispatcher->addListener(
            EventDispatcherFake::EVENT_INVALID,
            function () {
            }
        );
    }

    public function testValidNameDispatch()
    {
        $this->dispatcher->doDispatch(EventDispatcherFake::EVENT_VALID, new Event());

        // no exceptions should be thrown
        $this->assertTrue(true);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidNameDispatchThrowsAnException()
    {
        $this->dispatcher->doDispatch(EventDispatcherFake::EVENT_INVALID, new Event());
    }
}
