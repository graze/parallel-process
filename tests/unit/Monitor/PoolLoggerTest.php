<?php

namespace Graze\ParallelProcess\Test\Unit\Display;

use Graze\ParallelProcess\CallbackRun;
use Graze\ParallelProcess\Monitor\PoolLogger;
use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\Test\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Debug\BufferingLogger;

class PoolLoggerTest extends TestCase
{
    /** @var BufferingLogger */
    private $logger;
    /** @var PoolLogger */
    private $monitor;

    public function setUp()
    {
        $this->logger = new BufferingLogger();
        $this->monitor = new PoolLogger($this->logger);
    }

    public function testRunStarted()
    {
        $run = new CallbackRun(
            function () {
                return true;
            },
            ['key' => 'value']
        );
        $this->monitor->monitor($run);

        $run->start();

        $logs = $this->logger->cleanLogs();

        $this->assertCount(3, $logs); // add, successful, complete
        list($level, $message, $context) = reset($logs);
        $this->assertEquals(LogLevel::DEBUG, $level);
        $this->assertRegExp('/^run \[[\\a-zA-Z0-9]+\:\d+\]: has started$/i', $message);
        $this->assertArraySubset(
            [
                'run' => [
                    'tags' => ['key' => 'value'], 'hasStarted' => true, 'isRunning' => false, 'isSuccessful' => false,
                ],
            ],
            $context
        );
    }

    public function testRunSuccessful()
    {
        $run = new CallbackRun(
            function () {
                return true;
            },
            ['key' => 'value']
        );
        $this->monitor->monitor($run);

        $run->start();

        $logs = $this->logger->cleanLogs();

        $this->assertCount(3, $logs); // add, successful, complete
        list($level, $message, $context) = $logs[1];
        $this->assertEquals(LogLevel::DEBUG, $level);
        $this->assertRegExp('/^run \[[\\a-zA-Z0-9]+\:\d+\]: successfully finished$/i', $message);
        $this->assertArraySubset(
            [
                'run' => [
                    'tags' => ['key' => 'value'], 'hasStarted' => true, 'isRunning' => false, 'isSuccessful' => true,
                ],
            ],
            $context
        );
    }

    public function testRunFailed()
    {
        $exception = new \RuntimeException('failed');
        $run = new CallbackRun(
            function () use ($exception) {
                throw $exception;
            },
            ['key' => 'value']
        );
        $this->monitor->monitor($run);

        $run->start();

        $logs = $this->logger->cleanLogs();

        $this->assertCount(3, $logs); // add, failed, complete
        list($level, $message, $context) = $logs[1];
        $this->assertEquals(LogLevel::DEBUG, $level);
        $this->assertRegExp('/^run \[[\\a-zA-Z0-9]+\:\d+\]: failed - failed$/i', $message);
        $this->assertArraySubset(
            [
                'run'    => [
                    'tags' => ['key' => 'value'], 'hasStarted' => true, 'isRunning' => false, 'isSuccessful' => false,
                ],
                'errors' => ['failed'],
            ],
            $context
        );
    }

    public function testRunCompleted()
    {
        $run = new CallbackRun(
            function () {
                return true;
            },
            ['key' => 'value']
        );
        $this->monitor->monitor($run);

        $run->start();

        $logs = $this->logger->cleanLogs();

        $this->assertCount(3, $logs); // add, successful, complete
        list($level, $message, $context) = $logs[2];
        $this->assertEquals(LogLevel::DEBUG, $level);
        $this->assertRegExp('/^run \[[\\a-zA-Z0-9]+\:\d+\]: has finished running$/i', $message);
        $this->assertArraySubset(
            [
                'run' => [
                    'tags' => ['key' => 'value'], 'hasStarted' => true, 'isRunning' => false, 'isSuccessful' => true,
                ],
            ],
            $context
        );
    }

    public function testPoolRunAdded()
    {
        $pool = new Pool();
        $run = new CallbackRun(
            function () {
                return true;
            },
            ['key' => 'value']
        );

        $this->monitor->monitor($pool);

        $pool->add($run);

        $logs = $this->logger->cleanLogs();

        $this->assertCount(1, $logs);
        list($level, $message, $context) = $logs[0];
        $this->assertEquals(LogLevel::DEBUG, $level);
        $this->assertRegExp('/^pool \[[\\a-zA-Z0-9]+\:\d+\]: run \[[\\a-zA-Z0-9]+\:\d+\] has been added$/i', $message);
        $this->assertArraySubset(
            [
                'pool' => [
                    'tags'        => [], 'hasStarted' => false, 'isRunning' => false, 'isSuccessful' => false,
                    'duration'    => 0, 'progress' => 0.0,
                    'num_waiting' => 1, 'num_running' => 0, 'num_finished' => 0,
                ],
                'run'  => [
                    'tags'         => ['key' => 'value'], 'hasStarted' => false, 'isRunning' => false,
                    'isSuccessful' => false, 'duration' => 0, 'progress' => null,
                ],
            ],
            $context
        );
    }

    public function testPoolRunUpdatedOnRunStateChange()
    {
        $pool = new Pool();
        $run = new CallbackRun(
            function () {
                return true;
            },
            ['key' => 'value']
        );

        $pool->add($run);

        $this->monitor->monitor($pool);

        $run->start();

        $logs = $this->logger->cleanLogs();

        $this->assertCount(8, $logs);
        list($level, $message, $context) = $logs[1];
        $this->assertEquals(LogLevel::DEBUG, $level);
        $this->assertRegExp('/^pool \[[\\a-zA-Z0-9]+\:\d+\]: updated$/i', $message);
        $this->assertArraySubset(
            [
                'pool' => [
                    'tags'        => [], 'hasStarted' => true, 'isRunning' => true, 'isSuccessful' => false,
                    'progress'    => 0.0,
                    'num_waiting' => 0, 'num_running' => 1, 'num_finished' => 0,
                ],
            ],
            $context
        );

        list($level, $message, $context) = $logs[6];
        $this->assertEquals(LogLevel::DEBUG, $level);
        $this->assertRegExp('/^run \[[\\a-zA-Z0-9]+\:\d+\]: has finished running$/i', $message);
        $this->assertArraySubset(
            [
                'pool' => [
                    'type'         => Pool::class, 'tags' => [], 'hasStarted' => true, 'isRunning' => false,
                    'isSuccessful' => true, 'progress' => 1.0,
                    'num_waiting'  => 0, 'num_running' => 0, 'num_finished' => 1,
                ],
            ],
            $context
        );
    }
}
