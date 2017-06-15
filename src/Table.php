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
use Graze\BufferedConsole\BufferedConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Table
{
    const SPINNER = "⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏";

    /** @var Pool */
    private $processPool;
    /** @var string[] */
    private $rows = [];
    /** @var Exception[] */
    private $exceptions;
    /** @var int[] */
    private $maxLengths = [];
    /** @var BufferedConsoleOutput */
    private $output;
    /** @var int */
    private $durLength = 0;

    /**
     * Table constructor.
     *
     * @param ConsoleOutputInterface $output
     * @param Pool|null              $pool
     */
    public function __construct(ConsoleOutputInterface $output, Pool $pool = null)
    {
        $this->processPool = $pool ?: new Pool();
        if (!$output instanceof BufferedConsoleOutput) {
            $this->output = new BufferedConsoleOutput($output);
            $this->output->setTrim(true);
        } else {
            $this->output = $output;
        }
        $this->exceptions = [];
    }

    /**
     * Parses the rows to determine the key lengths to make a pretty table
     *
     * @param array $data
     */
    private function updateRowKeyLengths(array $data = [])
    {
        $lengths = array_map('mb_strlen', $data);

        $keys = array_merge(array_keys($lengths), array_keys($this->maxLengths));

        foreach ($keys as $key) {
            if (!isset($this->maxLengths[$key])
                || (isset($lengths[$key]) && $lengths[$key] > $this->maxLengths[$key])
            ) {
                $this->maxLengths[$key] = $lengths[$key];
            }
        }
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
        $info = [];
        foreach ($data as $key => $value) {
            $length = isset($this->maxLengths[$key]) ? '-' . $this->maxLengths[$key] : '';
            $info[] = sprintf("<info>%s</info>: %{$length}s", $key, $value);
        }
        $extra = $extra ? '   ' . $extra : '';
        return sprintf("%s (<comment>%6.2fs</comment>) %s%s", implode(' ', $info), $duration, $status, $extra);
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
                    $last
                );
                if ($spinner > mb_strlen(static::SPINNER) - 1) {
                    $spinner = 0;
                }
                $this->render();
            };
        } else {
            $onProgress = null;
        }

        $this->processPool->add(new Run(
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
        ));

        $this->updateRowKeyLengths($data);
    }

    /**
     * Render a specific row
     *
     * @param int $row
     */
    private function render($row = 0)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->output->reWrite($this->rows, true);
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

        if (count($this->exceptions) > 0) {
            foreach ($this->exceptions as $exception) {
                $this->output->writeln($exception->getMessage());
            }

            throw reset($this->exceptions);
        }

        return $output;
    }
}
