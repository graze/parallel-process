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

use Graze\ParallelProcess\Display\Table;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\Test\BufferDiffOutput;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TableTest extends TestCase
{
    /** @var BufferDiffOutput */
    private $bufferOutput;
    /** @var mixed */
    private $pool;
    /** @var Table */
    private $table;

    public function setUp()
    {
        mb_internal_encoding("UTF-8");
        $this->bufferOutput = new BufferDiffOutput();
        $this->pool = new PriorityPool();
        $this->table = new Table($this->bufferOutput, $this->pool);
    }

    public function testConstructWithNonBufferedOutput()
    {
        $output = Mockery::mock(ConsoleOutputInterface::class);
        $table = new Table($output);

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testShowOutput()
    {
        $output = Mockery::mock(ConsoleOutputInterface::class);
        $table = new Table($output);

        $this->assertTrue($table->isShowOutput());

        $this->assertSame($table, $table->setShowOutput(false));

        $this->assertFalse($table->isShowOutput());
    }

    public function testShowSummary()
    {
        $output = Mockery::mock(ConsoleOutputInterface::class);
        $table = new Table($output);

        $this->assertTrue($table->isShowSummary());

        $this->assertSame($table, $table->setShowSummary(false));

        $this->assertFalse($table->isShowSummary());
    }

    public function testSpinnerLoop()
    {
        $this->table->setShowSummary(false);
        $this->bufferOutput->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); //add, add2, start, run
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

        $this->table->run(0);

        $expected = [
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) %'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠋%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠙%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠹%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠸%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠼%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠴%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠦%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠧%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠇%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠏%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) ⠋%'],
            ['%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testProgressBar()
    {
        $this->table->setShowSummary(false);
        $this->bufferOutput->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, run
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add
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

        $run = Mockery::mock(ProcessRun::class, [$process, ['key' => 'value']])->makePartial();
        $this->pool->add($run);

        $run->allows()
            ->getProgress()
            ->andReturns(
                [0, 100, 0],
                [5, 100, 0.05],
                [10, 100, 0.1],
                [15, 100, 0.15],
                [20, 100, 0.2],
                [25, 100, 0.25],
                [30, 100, 0.3],
                [35, 100, 0.35],
                [40, 100, 0.4],
                [45, 100, 0.45],
                [50, 100, 0.5],
                [55, 100, 0.55],
                [60, 100, 0.6],
                [65, 100, 0.65],
                [70, 100, 0.7],
                [75, 100, 0.75],
                [80, 100, 0.8],
                [85, 100, 0.85],
                [90, 100, 0.9],
                [95, 100, 0.95],
                [100, 100, 1]
            );
        $run->allows()
            ->getDuration()
            ->andReturns(
                0,
                0.05,
                0.1,
                0.15,
                0.2,
                0.25,
                0.3,
                0.35,
                0.4,
                0.45,
                0.5,
                0.55,
                0.6,
                0.65,
                0.7,
                0.75,
                0.8,
                0.85,
                0.9,
                0.95,
                1
            );

        $this->table->run(0);

        $expected = [
            ['/<info>key<\/info>: value \(<comment>  0.00s<\/comment>\) /'],
            ['/<info>key<\/info>: value \(<comment>  0.00s<\/comment>\) ▕<comment>  <\/comment>▏<info>  0%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.05s<\/comment>\) ▕<comment>▏ <\/comment>▏<info>  5%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.10s<\/comment>\) ▕<comment>▎ <\/comment>▏<info> 10%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.15s<\/comment>\) ▕<comment>▍ <\/comment>▏<info> 15%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.20s<\/comment>\) ▕<comment>▍ <\/comment>▏<info> 20%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.25s<\/comment>\) ▕<comment>▌ <\/comment>▏<info> 25%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.30s<\/comment>\) ▕<comment>▋ <\/comment>▏<info> 30%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.35s<\/comment>\) ▕<comment>▋ <\/comment>▏<info> 35%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.40s<\/comment>\) ▕<comment>▊ <\/comment>▏<info> 40%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.45s<\/comment>\) ▕<comment>▉ <\/comment>▏<info> 45%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.50s<\/comment>\) ▕<comment>█ <\/comment>▏<info> 50%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.55s<\/comment>\) ▕<comment>█▏<\/comment>▏<info> 55%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.60s<\/comment>\) ▕<comment>█▎<\/comment>▏<info> 60%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.65s<\/comment>\) ▕<comment>█▍<\/comment>▏<info> 65%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.70s<\/comment>\) ▕<comment>█▍<\/comment>▏<info> 70%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.75s<\/comment>\) ▕<comment>█▌<\/comment>▏<info> 75%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.80s<\/comment>\) ▕<comment>█▋<\/comment>▏<info> 80%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.85s<\/comment>\) ▕<comment>█▋<\/comment>▏<info> 85%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.90s<\/comment>\) ▕<comment>█▊<\/comment>▏<info> 90%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  0.95s<\/comment>\) ▕<comment>█▉<\/comment>▏<info> 95%<\/info>/'],
            ['/<info>key<\/info>: value \(<comment>  1.00s<\/comment>\) <info>✓<\/info>/'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testValueDataArrayDoesNotShowTheKey()
    {
        $this->table->setShowSummary(false);
        $this->bufferOutput->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, start, run
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            false, // add2
            true,  // check
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);

        $this->pool->add($process, ['value', 'value2']);

        $this->table->run(0);

        $expected = [
            ['%value value2 \(<comment>[ 0-9\.s]+</comment>\) %'],
            ['%value value2 \(<comment>[ 0-9\.s]+</comment>\) ⠋%'],
            ['%value value2 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    public function testSummaryIsWaitingBeforeTheProcessStarts()
    {
        $this->bufferOutput->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $this->table->setShowOutput(true);
        $this->table->setShowSummary(true);

        $oneFails = false;

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->with(Mockery::on(function ($closure) {
            call_user_func($closure, Process::OUT, 'some text');
            return true;
        }))->once();
        $process->shouldReceive('isStarted')
                ->andReturn(false, false, false, false, true); // add, add2, summary, start, run
        $process->shouldReceive('isRunning')->andReturn(false, false, true, false); // add, add2, check, check
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);
        $process->shouldReceive('getOutput')->andReturn('some text');

        $this->pool->add($process, ['key' => 'value']);

        try {
            $this->table->run(0);
        } catch (\Exception $e) {
            if (!$oneFails || !$e instanceof ProcessFailedException) {
                throw $e;
            }
        }

        $expected = [
            [
                '%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) %',
                '%waiting...%',
            ],
            [
                '%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) %',
                '%<comment>Total</comment>:  1, <comment>Running</comment>:  1, <comment>Waiting</comment>:  0%',
            ],
            [
                '%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                '%<comment>Total</comment>:  1, <comment>Running</comment>:  1, <comment>Waiting</comment>:  0%',
            ],
            [
                '%<info>key</info>: value \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                '%^$%',
            ],
        ];

        $this->compareOutputs($expected, $this->bufferOutput->getWritten());
    }

    /**
     * Runs a series of processes, each doing initial state, single on progress run, single complete entry
     *
     * @dataProvider outputData
     *
     * @param int        $verbosity     OutputInterface::VERBOSITY_*
     * @param bool       $showOutput    Should it display some output text
     * @param bool       $showSummary   Should we show a summary
     * @param bool[]     $processStates an entry for each process to run, true = success, false = failure
     * @param string[][] $outputs       Regex patterns for the output string
     *
     * @throws \Exception
     */
    public function testOutput($verbosity, $showOutput, $showSummary, array $processStates, array $outputs)
    {
        $this->bufferOutput->setVerbosity($verbosity);
        $this->table->setShowOutput($showOutput);
        $this->table->setShowSummary($showSummary);

        $oneFails = false;

        for ($i = 0; $i < count($processStates); $i++) {
            $process = Mockery::mock(Process::class);
            $process->shouldReceive('stop');
            $process->shouldReceive('start')->with(Mockery::on(function ($closure) {
                call_user_func($closure, Process::OUT, 'some text');
                return true;
            }))->once();
            if ($showSummary) {
                $process->shouldReceive('isStarted')
                        ->andReturn(false, false, false, false, true); // add, add2, summary, start, run
            } else {
                $process->shouldReceive('isStarted')->andReturn(false, false, false, true); // add, add2, start, run
            }
            $process->shouldReceive('isRunning')->andReturn(false, false, true, false); // add, add2, check, check
            $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn($processStates[$i]);
            $process->shouldReceive('getOutput')->andReturn('some text');

            if (!$processStates[$i]) {
                $process->shouldReceive('getCommandLine')->andReturn('test');
                $process->shouldReceive('getExitCode')->andReturn(1);
                $process->shouldReceive('getExitCodeText')->andReturn('failed');
                $process->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
                $process->shouldReceive('isOutputDisabled')->andReturn(false);
                $process->shouldReceive('getErrorOutput')->andReturn('some error text');
                $oneFails = true;
            }

            $this->pool->add($process, ['key' => 'value', 'run' => $i]);
        }

        try {
            $this->table->run(0);
        } catch (\Exception $e) {
            if (!$oneFails || !$e instanceof ProcessFailedException) {
                throw $e;
            }
        }

        $this->compareOutputs($outputs, $this->bufferOutput->getWritten());
    }

    /**
     * @return array
     */
    public function outputData()
    {
        return [
            [ // verbose with single valid run
              OutputInterface::VERBOSITY_VERBOSE,
              false,
              false,
              [true],
              [
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) %'],
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%'],
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
              ],
            ],
            [ // normal verbosity only writes a single line
              OutputInterface::VERBOSITY_NORMAL,
              false,
              false,
              [true],
              [
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
              ],
            ],
            [
                OutputInterface::VERBOSITY_NORMAL,
                false,
                false,
                [true, true],
                [
                    ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
                    ['%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
                ],
            ],
            [ // multiple runs with verbosity will update each item one at a time
              OutputInterface::VERBOSITY_VERBOSE,
              false,
              false,
              [true, true],
              [
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) %',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) %',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) %',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                  ],
              ],
            ],
            [ // errors will display an error
              OutputInterface::VERBOSITY_VERBOSE,
              false,
              false,
              [false],
              [
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) %'],
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%'],
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <error>x</error>%'],
                  [
                      <<<DOC
%The command "test" failed.

Exit Code: 1\(failed\)

Working directory: /tmp

Output:
================
some text

Error Output:
================
some error text%
DOC
                      ,
                  ],
              ],
            ],
            [ // errors will display an error
              OutputInterface::VERBOSITY_NORMAL,
              false,
              false,
              [false],
              [
                  ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <error>x</error>%'],
                  [
                      <<<DOC
%The command "test" failed.

Exit Code: 1\(failed\)

Working directory: /tmp

Output:
================
some text

Error Output:
================
some error text%
DOC
                      ,
                  ],
              ],
            ],
            [ // multiple runs with verbosity will update each item one at a time
              OutputInterface::VERBOSITY_VERBOSE,
              false,
              false,
              [true, false],
              [
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) %',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) %',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) %',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) <error>x</error>%',
                  ],
                  [
                      <<<DOC
%The command "test" failed.

Exit Code: 1\(failed\)

Working directory: /tmp

Output:
================
some text

Error Output:
================
some error text%
DOC
                      ,
                  ],
              ],
            ],
            [ // include output
              OutputInterface::VERBOSITY_VERBOSE,
              true,
              false,
              [true],
              [
                  ['%(*UTF8)<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) %'],
                  ['%(*UTF8)<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]  some text%'],
                  ['%(*UTF8)<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>  some text%'],
              ],
            ],
            [ // include a summary
              OutputInterface::VERBOSITY_VERBOSE,
              false,
              true,
              [true, true],
              [
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) %',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) %',
                      '%^waiting...$%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) %',
                      '%<comment>Total</comment>:  2, <comment>Running</comment>:  2, <comment>Waiting</comment>:  0%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<comment>Total</comment>:  2, <comment>Running</comment>:  2, <comment>Waiting</comment>:  0%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                      '%<comment>Total</comment>:  2, <comment>Running</comment>:  2, <comment>Waiting</comment>:  0%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<comment>Total</comment>:  2, <comment>Running</comment>:  1, <comment>Waiting</comment>:  0%',
                  ],
                  [
                      '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%',
                      '%^$%',
                  ],
              ],
            ],
        ];
    }
}
