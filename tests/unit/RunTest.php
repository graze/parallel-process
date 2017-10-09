<?php

namespace Graze\ParallelProcess\Test\Unit;

use Graze\ParallelProcess\Run;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

class RunTest extends TestCase
{
    public function testRunImplementsRunInterface()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $run = new Run($process);
        $this->assertInstanceOf(RunInterface::class, $run);
    }

    public function testInitialState()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new Run($process);

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
    }

    public function testUpdateOnPoll()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new Run($process);

        $this->assertTrue($run->isUpdateOnPoll());
        $this->assertSame($run, $run->setUpdateOnPoll(false));
        $this->assertFalse($run->isUpdateOnPoll());
    }

    public function testUpdateOnProcessOutput()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new Run($process);

        $this->assertTrue($run->isUpdateOnProcessOutput());
        $this->assertSame($run, $run->setUpdateOnProcessOutput(false));
        $this->assertFalse($run->isUpdateOnProcessOutput());
    }

    public function testProcessRunning()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new Run($process);

        $process->shouldReceive('isRunning')
                ->andReturn(false, true);
        $process->shouldReceive('start');

        $run->start();

        $process->shouldReceive('isStarted')
                ->andReturn(true);

        $this->assertTrue($run->isRunning());
        $this->assertTrue($run->hasStarted());
        $this->assertFalse($run->isSuccessful());
    }

    public function testRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new Run($process);

        $process->shouldReceive('isRunning')
                ->andReturn(false, true, false);

        $process->shouldReceive('start');
        $run->start();

        $process->shouldReceive('isStarted')
                ->andReturn(true);
        $process->shouldReceive('isSuccessful')
                ->andReturn(true);

        $this->assertTrue($run->poll());
        $this->assertFalse($run->poll());
        $this->assertTrue($run->isSuccessful());
        $this->assertTrue($run->hasStarted());
    }

    public function testOnSuccess()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $hit = false;

        $run = new Run(
            $process,
            function ($proc) use ($process, &$hit) {
                $this->assertSame($proc, $process);
                $hit = true;
            }
        );

        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(true);
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

        $run = new Run(
            $process,
            null,
            function ($proc) use ($process, &$hit) {
                $this->assertSame($proc, $process);
                $hit = true;
            }
        );

        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(true);
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

        $run = new Run(
            $process,
            null,
            null,
            function ($proc) use ($process, &$hit) {
                $this->assertSame($proc, $process);
                $hit = true;
            }
        );

        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false, true, false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertTrue($run->poll());
        $this->assertFalse($run->poll());
    }

    public function testStartingAfterStartedWillDoNothing()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $run = new Run($process);

        $process->shouldReceive('isRunning')
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

        $run = new Run(
            $process,
            function ($proc, $dur, $last, $lastType) use ($process) {
                $this->assertSame($process, $proc);
                $this->assertInternalType('float', $dur);
                $this->assertEquals('some text', $last);
                $this->assertEquals(Process::OUT, $lastType);
            }
        );

        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertFalse($run->poll());
    }

    public function testUpdateOnPollOffDoesNotUpdateOnPoll()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');

        $process->shouldReceive('start')->once();

        $run = new Run(
            $process,
            null,
            null,
            function () {
                $this->fail('onProgress should not be called');
            }
        );
        $run->setUpdateOnPoll(false);

        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false, true, false);
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

        $run = new Run(
            $process,
            null,
            null,
            function () {
                $this->fail('onProgress should not be called');
            }
        );
        $run->setUpdateOnProcessOutput(false);

        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $run->start();
        $this->assertFalse($run->poll());
    }
}
