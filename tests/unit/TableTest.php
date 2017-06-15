<?php

namespace Graze\ParallelProcess\Test\Unit;

use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\Table;
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
    private $output;
    /** @var mixed */
    private $pool;
    /** @var Table */
    private $table;

    public function setUp()
    {
        $this->output = new BufferDiffOutput();
        $this->pool = Mockery::mock(Pool::class)->makePartial();
        $this->table = new Table($this->output, $this->pool);
    }

    public function testConstructWithNonBufferedOutput()
    {
        $output = Mockery::mock(ConsoleOutputInterface::class);
        $table = new Table($output);

        $this->assertInstanceOf(Table::class, $table);
    }

    public function testSpinnerLoop()
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('start')->once();
        $process->shouldReceive('isStarted')->andReturn(false, true);
        $process->shouldReceive('isRunning')->andReturn(
            false, // add
            true,  // start
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
            false // complete
        );
        $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn(true);

        $this->table->add($process, ['key' => 'value']);

        $this->table->run(0);

        $expected = [
            ['%<info>key</info>: value \(<comment>  0.00s</comment>\) %'],
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

        $this->compareOutputs($expected, $this->output->getWritten());
    }

    /**
     * Runs a series of processes, each doing initial state, single on progress run, single complete entry
     *
     * @dataProvider outputData
     *
     * @param int        $verbosity     OutputInterface::VERBOSITY_*
     * @param bool[]     $processStates an entry for each process to run, true = success, false = failure
     * @param string[][] $outputs       Regex patterns for the output string
     *
     * @throws \Exception
     * @throws mixed
     */
    public function testOutput($verbosity, array $processStates, array $outputs)
    {
        $this->output->setVerbosity($verbosity);

        $oneFails = false;

        for ($i = 0; $i < count($processStates); $i++) {
            $process = Mockery::mock(Process::class);
            $process->shouldReceive('stop');
            $process->shouldReceive('start')->once();
            $process->shouldReceive('isStarted')->andReturn(false, true);
            $process->shouldReceive('isRunning')->andReturn(false, true, false); // add, start, check, check
            $process->shouldReceive('isSuccessful')->atLeast()->once()->andReturn($processStates[$i]);

            if (!$processStates[$i]) {
                $process->shouldReceive('getCommandLine')->andReturn('test');
                $process->shouldReceive('getExitCode')->andReturn(1);
                $process->shouldReceive('getExitCodeText')->andReturn('failed');
                $process->shouldReceive('getWorkingDirectory')->andReturn('/tmp');
                $process->shouldReceive('isOutputDisabled')->andReturn(false);
                $process->shouldReceive('getOutput')->andReturn('some text');
                $process->shouldReceive('getErrorOutput')->andReturn('some error text');
                $oneFails = true;
            }

            $this->table->add($process, ['key' => 'value', 'run' => $i]);
        }

        try {
            $this->table->run(0);
        } catch (\Exception $e) {
            if (!$oneFails || !$e instanceof ProcessFailedException) {
                throw $e;
            }
        }

        $this->compareOutputs($outputs, $this->output->getWritten());
    }

    /**
     * Compare the outputs with an expected input.
     *
     * Each element in the array is a call to `write/writeln/reWrite`
     * Each element in the child array is a line to be written
     *
     * @param string[][] $expected Set of regular expressions to match against
     * @param string[][] $actual   The actual output
     */
    private function compareOutputs(array $expected, array $actual)
    {
        $this->assertSameSize($expected, $actual);

        for ($i = 0; $i < count($expected); $i++) {
            $this->assertSameSize($expected[$i], $actual[$i]);
            for ($j = 0; $j < count($expected[$i]); $j++) {
                $this->assertRegExp($expected[$i][$j], $actual[$i][$j]);
            }
        }
    }

    /**
     * @return array
     */
    public function outputData()
    {
        return [
            [ // verbose with single valid run
                OutputInterface::VERBOSITY_VERBOSE,
                [true],
                [
                    ['%<info>key</info>: value <info>run</info>: 0 \(<comment>  0.00s</comment>\) %'],
                    ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%'],
                    ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
                ],
            ],
            [ // normal verbosity only writes a single line
                OutputInterface::VERBOSITY_NORMAL,
                [true],
                [
                    ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
                ],
            ],
            [
                OutputInterface::VERBOSITY_NORMAL,
                [true, true],
                [
                    ['%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
                    ['%<info>key</info>: value <info>run</info>: 1 \(<comment>[ 0-9\.s]+</comment>\) <info>✓</info>%'],
                ],
            ],
            [ // multiple runs with verbosity will update each item one at a time
                OutputInterface::VERBOSITY_VERBOSE,
                [true, true],
                [
                    [
                        '%<info>key</info>: value <info>run</info>: 0 \(<comment>  0.00s</comment>\) %',
                        '%<info>key</info>: value <info>run</info>: 1 \(<comment>  0.00s</comment>\) %',
                    ],
                    [
                        '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                        '%<info>key</info>: value <info>run</info>: 1 \(<comment>  0.00s</comment>\) %',
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
                [false],
                [
                    ['%<info>key</info>: value <info>run</info>: 0 \(<comment>  0.00s</comment>\) %'],
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
                    ]
                ],
            ],
            [ // errors will display an error
                OutputInterface::VERBOSITY_NORMAL,
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
                    ]
                ],
            ],
            [ // multiple runs with verbosity will update each item one at a time
                OutputInterface::VERBOSITY_VERBOSE,
                [true, false],
                [
                    [
                        '%<info>key</info>: value <info>run</info>: 0 \(<comment>  0.00s</comment>\) %',
                        '%<info>key</info>: value <info>run</info>: 1 \(<comment>  0.00s</comment>\) %',
                    ],
                    [
                        '%<info>key</info>: value <info>run</info>: 0 \(<comment>[ 0-9\.s]+</comment>\) [⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]%',
                        '%<info>key</info>: value <info>run</info>: 1 \(<comment>  0.00s</comment>\) %',
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
                    ]
                ],
            ],
        ];
    }
}
