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

use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

class ProcessRunTest extends TestCase
{
    public function testRunImplementsRunInterface()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $run = new ProcessRun($process);
        $this->assertInstanceOf(RunInterface::class, $run);
    }

    public function testInitialState()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new ProcessRun($process);

        $this->assertSame($process, $run->getProcess());

        $process->shouldReceive('isRunning')
                ->andReturn(false);
        $process->shouldReceive('isStarted')
                ->andReturn(false);
        $process->shouldReceive('isSuccessful')
                ->andReturn(false);

        $this->assertFalse($run->isRunning(), 'should not be running');
        $this->assertFalse($run->hasStarted(), 'should not have started');
        $this->assertFalse($run->isSuccessful(), 'should not be successful');
        $this->assertTrue($run->isUpdateOnPoll(), 'update on poll should be on by deafult');
        $this->assertTrue($run->isUpdateOnProcessOutput(), 'update on poll should be on by deafult');
        $this->assertEquals([], $run->getExceptions(), 'no exceptions should be returned');
    }

    public function testUpdateOnPoll()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new ProcessRun($process);

        $this->assertTrue($run->isUpdateOnPoll());
        $this->assertSame($run, $run->setUpdateOnPoll(false));
        $this->assertFalse($run->isUpdateOnPoll());
    }

    public function testUpdateOnProcessOutput()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new ProcessRun($process);

        $this->assertTrue($run->isUpdateOnProcessOutput());
        $this->assertSame($run, $run->setUpdateOnProcessOutput(false));
        $this->assertFalse($run->isUpdateOnProcessOutput());
    }

    public function testProcessRunning()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new ProcessRun($process);

        $process->shouldReceive('isRunning')
                ->andReturn(true); // check
        $process->shouldReceive('start');

        $process->shouldReceive('isStarted')
                ->andReturn(false, true); // start, check

        $run->start();

        $this->assertTrue($run->isRunning());
        $this->assertTrue($run->hasStarted());
        $this->assertFalse($run->isSuccessful());
    }

    public function testRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new ProcessRun($process);

        $process->shouldReceive('isRunning')->andReturn(true, false);
        $process->shouldReceive('start');
        $process->shouldReceive('isStarted')->andReturn(false, true);

        $run->start();

        $process->shouldReceive('isSuccessful')->andReturn(true);

        $this->assertTrue($run->poll());
        $this->assertFalse($run->poll());
        $this->assertTrue($run->isSuccessful());
        $this->assertTrue($run->hasStarted());
    }

    public function testOnStart()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $hit = false;

        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testOnSuccess()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $hit = false;

        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::COMPLETED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testOnFailure()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $hit = false;

        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(false);

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testOnProgress()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $hit = false;

        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use (&$run, &$hit) {
                $this->assertSame($event->getRun(), $run);
                $hit = true;
            }
        );

        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(true, false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertTrue($run->poll());
        $this->assertFalse($run->poll());
    }

    public function testStartingAfterStartedWillDoNothing()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new ProcessRun($process);

        $process->shouldReceive('isStarted')
                ->andReturn(true);

        $this->assertSame($run, $run->start());
    }

    public function testEventsProvideDurationAndLastMessage()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $process->shouldReceive('start')
                ->with(
                    Mockery::on(
                        function (callable $fn) {
                            $this->assertNotNull($fn);
                            $fn(Process::OUT, 'some text');
                            return true;
                        }
                    )
                )
                ->once();

        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use (&$run, &$hits) {
                $this->assertSame($event->getRun(), $run);
                $this->assertInternalType('float', $run->getDuration());
                $this->assertEquals('some text', $run->getLastMessage());
                $this->assertEquals(Process::OUT, $run->getLastMessageType());
                $hits++;
            }
        );

        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testLastMessageHavingMultipleLinesReturnsOnePerLine()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $process->shouldReceive('start')
                ->with(
                    Mockery::on(
                        function (callable $fn) {
                            $this->assertNotNull($fn);
                            $fn(Process::OUT, "line 1\n\nline 2");
                            return true;
                        }
                    )
                )
                ->once();

        $hits = 0;
        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use (&$run, &$hits) {
                $this->assertSame($event->getRun(), $run);
                $this->assertInternalType('float', $run->getDuration());
                $this->assertContains($run->getLastMessage(), ['line 1', 'line 2']);
                $this->assertEquals(Process::OUT, $run->getLastMessageType());
                $hits++;
            }
        );

        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertFalse($run->poll());
        $this->assertEquals(2, $hits);
    }

    public function testUpdateOnPollOffDoesNotUpdateOnPoll()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $process->shouldReceive('start')->once();

        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::UPDATED,
            function () {
                $this->fail('onProgress should not be called');
            }
        );
        $run->setUpdateOnPoll(false);

        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(true, false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertTrue($run->poll());
        $this->assertFalse($run->poll());
    }

    public function testUpdateOnOutputOffDoesNotCallUpdateOnOutput()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $process->shouldReceive('start')
                ->with(
                    Mockery::on(
                        function (callable $fn) {
                            $this->assertNotNull($fn);
                            $fn(Process::STDOUT, 'some text');
                            return true;
                        }
                    )
                )
                ->once();

        $run = new ProcessRun($process);
        $run->addListener(
            RunEvent::UPDATED,
            function () {
                $this->fail('onProgress should not be called');
            }
        );
        $run->setUpdateOnProcessOutput(false);

        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testGetPriority()
    {
        $process = Mockery::mock(Process::class);
        $process->allows(['stop' => null, 'isStarted' => false]);
        $run = new ProcessRun($process, [], 1.5);

        $this->assertEquals(1.5, $run->getPriority());
        $this->assertSame($run, $run->setPriority(2));
        $this->assertEquals(2, $run->getPriority());
    }
}
