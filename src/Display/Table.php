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

namespace Graze\ParallelProcess\Display;

use Exception;
use Graze\DiffRenderer\DiffConsoleOutput;
use Graze\DiffRenderer\Terminal\TerminalInterface;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\OutputterInterface;
use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\RunInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Table
{
    use TagsTrait;

    const SPINNER = "⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏";

    /** @var PoolInterface */
    private $pool;
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
     * @param OutputInterface    $output
     * @param PoolInterface|null $pool
     */
    public function __construct(OutputInterface $output, PoolInterface $pool = null)
    {
        $this->pool = $pool ?: new PriorityPool();
        if (!$output instanceof DiffConsoleOutput) {
            $this->output = new DiffConsoleOutput($output);
            $this->output->setTrim(true);
        } else {
            $this->output = $output;
        }
        $this->terminal = $this->output->getTerminal();
        $this->exceptions = [];

        $this->pool->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) {
                $this->add($event->getRun());
            }
        );

        array_map([$this, 'add'], $this->pool->getAll());
    }

    /**
     * @param RunInterface $run
     * @param string       $status
     *
     * @return string
     */
    private function formatRow(RunInterface $run, $status)
    {
        $tags = $this->formatTags($run->getTags());
        $extra = ($this->showOutput && $run instanceof OutputterInterface && mb_strlen($run->getLastMessage()) > 0)
            ? '  ' . $this->terminal->filter($run->getLastMessage())
            : '';
        return sprintf("%s (<comment>%6.2fs</comment>) %s%s", $tags, $run->getDuration(), $status, $extra);
    }

    /**
     * @param RunInterface $run
     */
    public function add(RunInterface $run)
    {
        $index = count($this->rows);
        $this->rows[$index] = $this->formatRow($run, '');
        $spinner = 0;
        $bar = new TinyProgressBar(2, TinyProgressBar::FORMAT_DEFAULT, 1);

        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $run->addListener(
                RunEvent::UPDATED,
                function (RunEvent $event) use ($index, &$spinner, $bar) {
                    $run = $event->getRun();
                    $progress = $run->getProgress();
                    $status = (!is_null($progress))
                        ? $bar->setPosition($progress[2])
                              ->setPosition($progress[0])
                              ->setMax($progress[1])
                              ->render()
                        : mb_substr(static::SPINNER, $spinner++, 1);
                    $this->rows[$index] = $this->formatRow(
                        $run,
                        $status
                    );
                    if ($spinner > mb_strlen(static::SPINNER) - 1) {
                        $spinner = 0;
                    }
                    $this->render();
                }
            );
        }

        $run->addListener(
            RunEvent::SUCCESSFUL,
            function (RunEvent $event) use ($index, &$bar, &$spinner) {
                $this->rows[$index] = $this->formatRow($event->getRun(), "<info>✓</info>");
                $this->render($index);
            }
        );
        $run->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use ($index, &$bar, &$spinner) {
                $run = $event->getRun();
                $this->rows[$index] = $this->formatRow($run, "<error>x</error>");
                $this->render($index);
                $this->exceptions += $run->getExceptions();
            }
        );
        if ($run instanceof ProcessRun) {
            $run->setUpdateOnProcessOutput(false);
        }
        $this->updateRowKeyLengths($run->getTags());
    }

    /**
     * @return string
     */
    private function getSummary()
    {
        $running = count($this->pool->getRunning());
        $finished = count($this->pool->getFinished());
        if ($running > 0 || $finished > 0) {
            if ($running > 0) {
                return sprintf(
                    '<comment>Total</comment>: %2d, <comment>Running</comment>: %2d, <comment>Waiting</comment>: %2d',
                    $this->pool->count(),
                    $running,
                    count($this->pool->getWaiting())
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
    public function run($checkInterval = PriorityPool::CHECK_INTERVAL)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->render();
        }
        $output = $this->pool->run($checkInterval);
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
