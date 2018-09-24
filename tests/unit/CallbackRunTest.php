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

namespace Graze\ParallelProcess\Test\Unit;

use Graze\ParallelProcess\CallbackRun;
use Graze\ParallelProcess\Event\PriorityChangedEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\OutputterInterface;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\TestCase;
use RuntimeException;

class CallbackRunTest extends TestCase
{
    public function testCallbackRunImplementsRunInterface()
    {
        $run = new CallbackRun(function () {
            return true;
        });
        $this->assertInstanceOf(RunInterface::class, $run);
        $this->assertInstanceOf(OutputterInterface::class, $run);
    }

    public function testInitialState()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $this->assertFalse($run->isRunning(), 'should not be running');
        $this->assertFalse($run->hasStarted(), 'should not have started');
        $this->assertFalse($run->isSuccessful(), 'should not be successful');
        $this->assertEquals(0, $run->getDuration());
        $this->assertEquals([], $run->getExceptions(), 'no exceptions should be returned');
        $this->assertEquals([], $run->getTags(), 'no tags should be returned');
        $this->assertEquals('', $run->getLastMessageType());
        $this->assertEquals('', $run->getLastMessage());
    }

    public function testRun()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $run->start();

        $this->assertFalse($run->poll());
        $this->assertTrue($run->isSuccessful());
        $this->assertTrue($run->hasStarted());
        $this->assertFalse($run->isRunning());
        $this->assertGreaterThan(0, $run->getDuration());
    }

    public function testOnStart()
    {
        $hit = false;

        $run = new CallbackRun(function () {
            return true;
        });
        $run->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testOnSuccess()
    {
        $hit = false;

        $run = new CallbackRun(function () {
            return true;
        });
        $run->addListener(
            RunEvent::COMPLETED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testOnFailure()
    {
        $hit = false;

        $exception = new RuntimeException('some text');
        $run = new CallbackRun(function () use ($exception) {
            throw $exception;
        });
        $run->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $run->start();
        $this->assertFalse($run->poll());
        $this->assertEquals([$exception], $run->getExceptions());
    }

    /**
     * @requires PHP 7.0
     */
    public function testOnThrowableFailure()
    {
        $hit = false;

        $exception = new \TypeError();
        $run = new CallbackRun(function () use ($exception) {
            throw $exception;
        });
        $run->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $run->start();
        $this->assertFalse($run->poll());
        $this->assertEquals([$exception], $run->getExceptions());
    }

    public function testOnProgress()
    {
        $hit = false;

        $run = new CallbackRun(function () {
            return ['line 1', 'line 2'];
        });
        $run->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $this->assertContains($run->getLastMessage(), ['line 1', 'line 2']);
                $this->assertNull($run->getProgress());
                $hit = true;
            }
        );

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testEventsProvideDurationAndLastMessage()
    {
        $run = new CallbackRun(function () {
            return "line 1\nline 2";
        });
        $run->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use (&$run, &$hits) {
                $this->assertSame($event->getRun(), $run);
                $this->assertInternalType('float', $run->getDuration());
                $this->assertContains($run->getLastMessage(), ['line 1', 'line 2']);
                $hits++;
            }
        );

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testPriorityAccessors()
    {
        $run = new CallbackRun(
            function () {
                return true;
            },
            ['tag'],
            2.1
        );

        $hit = false;
        $run->addListener(
            PriorityChangedEvent::CHANGED,
            function (PriorityChangedEvent $event) use (&$hit, $run) {
                $hit = true;
                $this->assertSame($run, $event->getItem());
            }
        );
        $this->assertEquals(2.1, $run->getPriority());
        $this->assertFalse($hit);
        $this->assertSame($run, $run->setPriority(2.2));
        $this->assertTrue($hit);
        $this->assertEquals(2.2, $run->getPriority());

        $run->start();

        $hit = false;
        $this->assertFalse($hit);
        // don't trigger event if we have already started
        $this->assertSame($run, $run->setPriority(2.3));
        $this->assertFalse($hit);
        $this->assertEquals(2.3, $run->getPriority());
    }
}
