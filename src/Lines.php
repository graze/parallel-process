<?php

namespace Graze\ParallelProcess;

use Graze\DiffRenderer\DiffConsoleOutput;
use Graze\DiffRenderer\Terminal\TerminalInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Lines
{
    use TagsTrait;

    /** @var OutputInterface */
    private $output;
    /** @var TerminalInterface */
    private $terminal;
    /** @var Pool */
    private $processPool;
    /** @var bool */
    private $showDuration = true;
    /** @var bool */
    private $showType = true;
    /** @var bool */
    private $colourProcesses = true;
    /** @var string[] */
    private $colours = ['red', 'green', 'blue', 'yellow', 'magenta', 'white', 'cyan'];

    /**
     * Stream constructor.
     *
     * @param OutputInterface $output
     * @param Pool|null       $pool
     */
    public function __construct(OutputInterface $output, Pool $pool = null)
    {
        $this->output = $output;
        if (!$output instanceof DiffConsoleOutput) {
            $this->output = new DiffConsoleOutput($output);
            $this->output->setTrim(false);
        } else {
            $this->output = $output;
        }
        $this->terminal = $this->output->getTerminal();
        $this->processPool = $pool ?: new Pool();
    }

    /**
     * Show the running duration of each process
     *
     * @param bool $showDuration
     *
     * @return $this
     */
    public function setShowDuration($showDuration)
    {
        $this->showDuration = $showDuration;
        return $this;
    }

    /**
     * @return bool
     */
    public function isShowDuration()
    {
        return $this->showDuration;
    }

    /**
     * Show the type of each output (`out` or `err`)
     *
     * @param bool $showType
     *
     * @return $this
     */
    public function setShowType($showType)
    {
        $this->showType = $showType;
        return $this;
    }

    /**
     * @return bool
     */
    public function isShowType()
    {
        return $this->showType;
    }

    /**
     * Set weather this should colour each process with a different colour
     *
     * @param bool $colourProcesses
     *
     * @return $this
     */
    public function setColourProcesses($colourProcesses)
    {
        $this->colourProcesses = $colourProcesses;
        return $this;
    }

    /**
     * Does this colour each process with a different colour
     *
     * @return bool
     */
    public function isColourProcesses()
    {
        return $this->colourProcesses;
    }

    /**
     * @param Process $process
     * @param array   $data
     */
    public function add(Process $process, array $data = [])
    {
        $index = $this->processPool->count();
        $onProgress = function (Process $process, $duration, $last, $lastType) use ($index, $data) {
            $message = ($this->showType ? sprintf('(%s) %s', $lastType, $last) : $last);
            $this->output->writeln($this->format($index, $data, $duration, $message));
        };

        $run = new Run(
            $process,
            function (Process $process, $duration) use ($index, $data) {
                $this->output->writeln($this->format($index, $data, $duration, "<info>✓ Succeeded</info>"));
            },
            function (Process $process, $duration) use ($index, $data) {
                $this->output->writeln(
                    $this->format(
                        $index,
                        $data,
                        $duration,
                        sprintf(
                            "<error>x Failed</error> (code: %d) %s",
                            $process->getExitCode(),
                            $process->getExitCodeText()
                        )
                    )
                );
                $this->output->writeln((new ProcessFailedException($process))->getMessage());
            },
            $onProgress,
            function (Process $process, $duration) use ($index, $data) {
                $this->output->writeln($this->format($index, $data, $duration, "<fg=blue>→ Started</>"));
            }
        );
        $run->setUpdateOnPoll(false);
        $this->processPool->add($run);

        $this->updateRowKeyLengths($data);
    }

    /**
     * @param int    $index
     * @param array  $data
     * @param float  $duration
     * @param string $message
     *
     * @return string
     */
    private function format($index, array $data, $duration, $message = '')
    {
        $output = $this->formatTags(
            $data,
            ($this->colourProcesses ? $this->colours[$index % count($this->colours)] : null)
        );
        if ($this->showDuration) {
            $output .= sprintf(' (<comment>%6.2fs</comment>)', $duration);
        }

        return sprintf("%s %s", $output, $this->terminal->filter($message));
    }

    /**
     * @param float $checkInterval
     *
     * @return bool true if all processes were successful
     */
    public function run($checkInterval = Pool::CHECK_INTERVAL)
    {
        return $this->processPool->run($checkInterval);
    }
}
