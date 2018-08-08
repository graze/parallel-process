<?php

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
