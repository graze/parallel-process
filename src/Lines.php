<?php

namespace Graze\ParallelProcess;

use Graze\DiffRenderer\DiffConsoleOutput;
use Graze\DiffRenderer\Terminal\TerminalInterface;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

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
    private $showProgress = true;
    /** @var bool */
    private $colourProcesses = true;
    /** @var string[] */
    private $colours = ['red', 'green', 'blue', 'yellow', 'magenta', 'white', 'cyan'];
    /** @var TinyProgressBar|null */
    private $bar = null;

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

        $this->processPool->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) {
                $this->add($event->getRun());
            }
        );

        array_map([$this, 'add'], $this->processPool->getAll());
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
     * @return bool
     */
    public function isShowProgress()
    {
        return $this->showProgress;
    }

    /**
     * @param bool $showProgress
     *
     * @return Lines
     */
    public function setShowProgress($showProgress)
    {
        $this->showProgress = $showProgress;
        return $this;
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
     * @param RunInterface $run
     */
    public function add(RunInterface $run)
    {
        $index = $this->processPool->count();
        $run->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use ($index) {
                $run = $event->getRun();
                $message = '';
                if ($run instanceof Run) {
                    $message = ($this->showType && $run instanceof Run)
                        ? sprintf('(%s) %s', $run->getLastMessageType(), $run->getLastMessage())
                        : $run->getLastMessage();
                }
                $this->output->writeln($this->format($index, $run, $message));
            }
        );
        $run->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) use ($index) {
                $run = $event->getRun();
                $this->output->writeln(
                    $this->format($index, $run, "<fg=blue>→ Started</>")
                );
            }
        );
        $run->addListener(
            RunEvent::COMPLETED,
            function (RunEvent $event) use ($index) {
                $run = $event->getRun();
                $this->output->writeln(
                    $this->format($index, $run, "<info>✓ Succeeded</info>")
                );
            }
        );
        $run->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use ($index) {
                $run = $event->getRun();
                $process = null;
                if ($run instanceof Run) {
                    $process = $run->getProcess();
                    $error = sprintf(
                        "<error>x Failed</error> (code: %d) %s",
                        $process->getExitCode(),
                        $process->getExitCodeText()
                    );
                } else {
                    $error = "<error>x Failed</error>";
                }
                $this->output->writeln(
                    $this->format(
                        $index,
                        $run,
                        $error
                    )
                );
                if ($process) {
                    $this->output->writeln((new ProcessFailedException($process))->getMessage());
                }
            }
        );

        if ($run instanceof Run) {
            $run->setUpdateOnPoll(false);
        }
        $this->updateRowKeyLengths($run->getTags());
    }

    /**
     * @param int          $index
     * @param RunInterface $run
     * @param string       $message
     *
     * @return string
     */
    private function format($index, RunInterface $run, $message = '')
    {
        $output = $this->formatTags(
            $run->getTags(),
            ($this->colourProcesses ? $this->colours[$index % count($this->colours)] : null)
        );
        if ($this->showDuration) {
            $output .= sprintf(' (<comment>%6.2fs</comment>)', $run->getDuration());
        }
        $progress = $run->getProgress();
        if ($this->showProgress && !is_null($progress)) {
            if (is_null($this->bar)) {
                $this->bar = new TinyProgressBar(2, TinyProgressBar::FORMAT_SHORT, 1);
            }
            $output .= $this->bar->setPosition($progress[2])
                                 ->setPosition($progress[0])
                                 ->setMax($progress[1])
                                 ->render();
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
