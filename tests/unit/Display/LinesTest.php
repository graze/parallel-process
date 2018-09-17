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

namespace Graze\ParallelProcess\Test\Unit\Display;

use Graze\ParallelProcess\CallbackRun;
use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\Display\Lines;
use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\BufferDiffOutput;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class LinesTest extends TestCase
{
    /** @var BufferDiffOutput */
    private $bufferOutput;
    /** @var mixed */
    private $pool;
    /** @var Lines */
    private $lines;

    public function setUp()
    {
        mb_internal_encoding("UTF-8");
        $this->bufferOutput = new BufferDiffOutput();
        $this->pool = new PriorityPool();
        $this->lines = new Lines($this->bufferOutput, $this->pool);
    }

    public function testShowDuration()
    {
        $output = Mockery::mock(OutputInterface::class);
        $lines = new Lines($output);

        $this->assertTrue($lines->isShowDuration());

        $this->assertSame($lines, $lines->setShowDuration(false));

        $this->assertFalse($lines->isShowDuration());
    }

    public function testShowType()
    {
        $output = Mockery::mock(OutputInterface::class);
        $lines = new Lines($output);

        $this->assertTrue($lines->isShowType());

        $this->assertSame($lines, $lines->setShowType(false));

        $this->assertFalse($lines->isShowType());
    }

    public function testShowProcessColours()
    {
        $output = Mockery::mock(OutputInterface::class);
        $lines = new Lines($output);

        $this->assertTrue($lines->isColourProcesses());

        $this->assertSame($lines, $lines->setColourProcesses(false));

        $this->assertFalse($lines->isColourProcesses());
    }

    public function testSingleProcessOutput()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    call_user_func($closure, Process::OUT, 'second line');
                    call_user_func($closure, Process::OUT, 'third line');
                    call_user_func($closure, Process::ERR, 'error line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, start, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);

        $this->pool->add($process, ['key' => 'value']);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) \(out\) first line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) \(out\) second line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) \(out\) third line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) \(err\) error line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <info>✓ Succeeded</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testProcessColoursDisabled()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    call_user_func($closure, Process::OUT, 'second line');
                    call_user_func($closure, Process::OUT, 'third line');
                    call_user_func($closure, Process::ERR, 'error line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);

        $this->lines->setColourProcesses(false);
        $this->pool->add($process, ['key' => 'value']);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) \(out\) first line%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) \(out\) second line%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) \(out\) third line%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) \(err\) error line%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) <info>✓ Succeeded</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testValueOnlyData()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);

        $this->pool->add($process, ['value']);

        $this->lines->run(0);

        $expected = [
            ['%<options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) \(out\) first line%'],
            ['%<options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <info>✓ Succeeded</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testFailureReturnsErrors()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(false);
        $process->shouldReceive('getExitCode')->atLeast()->once()->andReturn(3);
        $process->shouldReceive('getExitCodeText')->atLeast()->once()->andReturn('some error');
        $process->shouldReceive('getCommandLine')->andReturn('test');
        $process->shouldReceive('getExitCode')->andReturn(3);
        $process->shouldReceive('getExitCodeText')->andReturn('some error');
        $process->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
        $process->shouldReceive('isOutputDisabled')->andReturn(false);
        $process->shouldReceive('getErrorOutput')->andReturn('some error text');
        $process->shouldReceive('getOutput')->andReturn('first line');

        $this->pool->add($process, ['key' => 'value']);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) \(out\) first line%'],
            [
                <<<TEXT
%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <error>x Failed</error> \(0\) The command "test" failed.

Exit Code: 3\(some error\)

Working directory: /tmp

Output:
================
first line

Error Output:
================
some error text%
TEXT
                ,
            ],
            [
                <<<TEXT
%The command "test" failed.

Exit Code: 3\(some error\)

Working directory: /tmp

Output:
================
first line

Error Output:
================
some error text%
TEXT
                ,
            ],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testShowTypeDoesNotOutputTheStdOrErrInformation()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);
        $process->shouldReceive('getOutput')->andReturn('first line');

        $this->lines->setShowType(false);
        $this->pool->add($process, ['key' => 'value']);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) first line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <info>✓ Succeeded</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testShowDurationToFalseDoesNotShowTheDuration()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);
        $process->shouldReceive('getOutput')->andReturn('first line');

        $this->lines->setShowDuration(false);
        $this->pool->add($process, ['key' => 'value']);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(out\) first line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> <info>✓ Succeeded</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testShowProgressShowsTheProgress()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);
        $process->shouldReceive('getOutput')->andReturn('first line');

        $run = Mockery::mock(ProcessRun::class, [$process, ['key' => 'value']])->makePartial();
        $run->allows()
            ->getProgress()
            ->andReturns([0, 100, 0], [50, 100, 0.5], [100, 100, 1]);

        $this->lines->setShowProgress(true);
        $this->pool->add($run);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) |<comment>  </comment>| <fg=black;bg=cyan>  0\%</> <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) |<comment>█ </comment>| <fg=black;bg=cyan> 50\%</> \(out\) first line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) |<comment>██</comment>| <fg=black;bg=cyan>100\%</> <info>✓ Succeeded</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testNonShowProgressShowsTheProgress()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(
            Mockery::on(
                function ($closure) {
                    call_user_func($closure, Process::OUT, 'first line');
                    return true;
                }
            )
        )->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, check
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);
        $process->shouldReceive('getOutput')->andReturn('first line');

        $run = Mockery::mock(ProcessRun::class, [$process, ['key' => 'value']])->makePartial();
        $run->allows()
            ->getProgress()
            ->andReturns([0, 100, 0], [50, 100, 0.5], [100, 100, 1]);

        $this->lines->setShowProgress(false);
        $this->assertFalse($this->lines->isShowProgress());
        $this->pool->add($run);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) \(out\) first line%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <info>✓ Succeeded</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testNonProcessRunFailure()
    {
        $exception = new RuntimeException('some error', 5);
        $run = new CallbackRun(
            function () use ($exception) {
                throw $exception;
            },
            ['key' => 'value']
        );

        $this->pool->add($run);

        $this->lines->run(0);

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <error>x Failed</error> \(5\) some error%'],
            ['%some error%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testRunFailureWithNoException()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('stop');
        $run->shouldReceive('start')->once();
        $run->shouldReceive('hasStarted')->andReturn(false, false, false, true);
        $run->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $run->shouldReceive('poll')->andReturn(
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $run->shouldReceive('isSuccessful')->atLeast()->andReturn(false);
        $run->shouldReceive('getPriority')->atLeast()->once()->andReturn(1.0);

        $startedEvent = $completedEvent = $updatedEvent = $successfulEvent = null;
        $failedEvents = [];

        $run->allows()->addListener(
            RunEvent::STARTED,
            Mockery::on(function (callable $callback) use (&$startedEvent) {
                $startedEvent = $callback;
                return true;
            })
        );
        $run->allows()->addListener(
            RunEvent::COMPLETED,
            Mockery::on(function (callable $callback) use (&$completedEvent) {
                $completedEvent = $callback;
                return true;
            })
        );
        $run->allows()->addListener(
            RunEvent::FAILED,
            Mockery::on(function (callable $callback) use (&$failedEvents) {
                $failedEvents[] = $callback;
                return true;
            })
        );
        $run->allows()->addListener(
            RunEvent::UPDATED,
            Mockery::on(function (callable $callback) use (&$updatedEvent) {
                $updatedEvent = $callback;
                return true;
            })
        );
        $run->allows()->addListener(
            RunEvent::SUCCESSFUL,
            Mockery::on(function (callable $callback) use (&$successfulEvent) {
                $successfulEvent = $callback;
                return true;
            })
        );
        $run->allows(['getTags' => ['key' => 'value'], 'getProgress' => null]);

        $this->pool->add($run);

        $this->lines->run(0);

        $run->allows()->getDuration()->andReturns(0, 0.1);

        $run->allows()->getExceptions()->andReturns([]);

        call_user_func($startedEvent, new RunEvent($run));
        foreach ($failedEvents as $failedEvent) {
            call_user_func($failedEvent, new RunEvent($run));
        }
        call_user_func($completedEvent, new RunEvent($run));

        $expected = [
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <fg=blue>→ Started</>%'],
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <error>x Failed</error>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }
}
