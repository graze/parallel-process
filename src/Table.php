<?php
/**
 * This file is part of graze/parallel-process.
 *
 * Copyright (c) 2017 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

namespace Graze\ParallelProcess;

use Exception;
use Graze\DiffRenderer\DiffConsoleOutput;
use Graze\DiffRenderer\Terminal\TerminalInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Table
{
    use TagsTrait;

    const SPINNER = "⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏";

    /** @var Pool */
    private $processPool;
    /** @var string[] */
    private $rows = [];
    /** @var Exception[] */
    private $exceptions;
    /** @var DiffConsoleOutput */
    private $output;
    /** @var TerminalInterface */
    private $terminal;
    /** @var bool */
    private $showOutput = true;
    /** @var bool */
    private $showSummary = true;

    /**
     * Table constructor.
     *
     * @param OutputInterface $output
     * @param Pool|null       $pool
     */
    public function __construct(OutputInterface $output, Pool $pool = null)
    {
        $this->processPool = $pool ?: new Pool();
        if (!$output instanceof DiffConsoleOutput) {
            $this->output = new DiffConsoleOutput($output);
            $this->output->setTrim(true);
        } else {
            $this->output = $output;
        }
        $this->terminal = $this->output->getTerminal();
        $this->exceptions = [];
    }

    /**
     * @param array  $data
     * @param string $status
     * @param float  $duration
     * @param string $extra
     *
     * @return string
     */
    private function formatRow(array $data, $status, $duration, $extra = '')
    {
        $tags = $this->formatTags($data);
        $extra = $extra ? '  ' . $this->terminal->filter($extra) : '';
        return sprintf("%s (<comment>%6.2fs</comment>) %s%s", $tags, $duration, $status, $extra);
    }

    /**
     * @param Process $process
     * @param array   $data
     */
    public function add(Process $process, array $data = [])
    {
        $index = count($this->rows);
        $this->rows[$index] = $this->formatRow($data, '', 0);
        $spinner = 0;

        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $onProgress = function ($process, $duration, $last) use ($index, $data, &$spinner) {
                $this->rows[$index] = $this->formatRow(
                    $data,
                    mb_substr(static::SPINNER, $spinner++, 1),
                    $duration,
                    ($this->showOutput ? $last : '')
                );
                if ($spinner > mb_strlen(static::SPINNER) - 1) {
                    $spinner = 0;
                }
                $this->render();
            };
        } else {
            $onProgress = null;
        }

        $run = new Run(
            $process,
            function ($process, $duration, $last) use ($index, $data) {
                $this->rows[$index] = $this->formatRow($data, "<info>✓</info>", $duration, $last);
                $this->render($index);
            },
            function ($process, $duration, $last) use ($index, $data) {
                $this->rows[$index] = $this->formatRow($data, "<error>x</error>", $duration, $last);
                $this->render($index);
                $this->exceptions[] = new ProcessFailedException($process);
            },
            $onProgress
        );
        $run->setUpdateOnProcessOutput(false);
        $this->processPool->add($run);

        $this->updateRowKeyLengths($data);
    }

    /**
     * @return string
     */
    private function getSummary()
    {
        if ($this->processPool->hasStarted()) {
            if ($this->processPool->isRunning()) {
                return sprintf(
                    '<comment>Total</comment>: %2d, <comment>Running</comment>: %2d, <comment>Waiting</comment>: %2d',
                    $this->processPool->count(),
                    count($this->processPool->getRunning()),
                    count($this->processPool->getWaiting())
                );
            } else {
                return '';
            }
        } else {
            return 'waiting...';
        }
    }

    /**
     * Render a specific row
     *
     * @param int $row
     */
    private function render($row = 0)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $rows = ($this->showSummary ? array_merge($this->rows, [$this->getSummary()]) : $this->rows);
            $this->output->reWrite($rows, !$this->showSummary);
        } else {
            $this->output->writeln($this->rows[$row]);
        }
    }

    /**
     * @param float $checkInterval
     *
     * @return bool true if all processes were successful
     * @throws Exception
     */
    public function run($checkInterval = Pool::CHECK_INTERVAL)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->render();
        }
        $output = $this->processPool->run($checkInterval);
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && $this->showSummary) {
            $this->render();
        }

        if (count($this->exceptions) > 0) {
            foreach ($this->exceptions as $exception) {
                $this->output->writeln($exception->getMessage());
            }

            throw reset($this->exceptions);
        }

        return $output;
    }

    /**
     * @return bool
     */
    public function isShowOutput()
    {
        return $this->showOutput;
    }

    /**
     * @param bool $showOutput
     *
     * @return $this
     */
    public function setShowOutput($showOutput)
    {
        $this->showOutput = $showOutput;
        return $this;
    }

    /**
     * @return bool
     */
    public function isShowSummary()
    {
        return $this->showSummary;
    }

    /**
     * @param bool $showSummary
     *
     * @return $this
     */
    public function setShowSummary($showSummary)
    {
        $this->showSummary = $showSummary;
        return $this;
    }
}
