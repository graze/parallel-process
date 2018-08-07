<?php

namespace Graze\ParallelProcess\Test\Unit;

use Graze\ParallelProcess\Lines;
use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\Run;
use Graze\ParallelProcess\Test\BufferDiffOutput;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
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
        $this->pool = Mockery::mock(Pool::class)->makePartial();
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
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // start
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
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // start
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
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // start
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
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // start
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
            ['%<info>key</info>: <options=bold;fg=\w+>value</> \(<comment>[ 0-9\.s]+</comment>\) <error>x Failed</error> \(code: 3\) some error%'],
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
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // start
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
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // start
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
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // start
            true,  // check
            true,  // ...
            true,
            true,
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);
        $process->shouldReceive('getOutput')->andReturn('first line');

        $run = Mockery::mock(Run::class, [$process, ['key' => 'value']])->makePartial();
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
}
