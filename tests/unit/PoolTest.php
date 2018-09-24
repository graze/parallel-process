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

namespace Graze\ParallelProcess\Test\Unit;

use Graze\DataStructure\Collection\CollectionInterface;
use Graze\ParallelProcess\CallbackRun;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

class PoolTest extends TestCase
{
    /** @var mixed */
    private $process;

    public function setUp()
    {
        parent::setUp();

        $this->process = Mockery::mock(Process::class)
                                ->allows(['stop' => null, 'isStarted' => false, 'isRunning' => false]);
    }

    public function testPoolIsARunInterface()
    {
        $pool = new Pool();
        $this->assertInstanceOf(RunInterface::class, $pool);
    }

    public function testPoolIsAPoolInterface()
    {
        $pool = new Pool();
        $this->assertInstanceOf(PoolInterface::class, $pool);
    }

    public function testPoolIsACollectionOfRuns()
    {
        $pool = new Pool();
        $this->assertInstanceOf(CollectionInterface::class, $pool);

        $this->assertSame($pool, $pool->add($this->process));

        $runs = $pool->getAll();
        $this->assertCount(1, $runs);

        $this->assertInstanceOf(ProcessRun::class, reset($runs));
    }

    public function testPoolInitialStateWithProcess()
    {
        $pool = new Pool();
        $pool->add($this->process);

        $this->assertFalse($pool->isSuccessful());
        $this->assertFalse($pool->isRunning());
        $this->assertFalse($pool->hasStarted());
    }

    public function testPoolConstructor()
    {
        $runs = [];
        for ($i = 0; $i < 2; $i++) {
            $runs[] = Mockery::mock(RunInterface::class)
                             ->allows(['isRunning' => false, 'hasStarted' => false, 'addListener' => true, 'getPriority' => 1.0]);
        }

        $pool = new Pool($runs);

        $this->assertEquals(2, $pool->count());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddingNonRunInterfaceWillThrowException()
    {
        $nope = Mockery::mock();
        $pool = new Pool();
        $pool->add($nope);
    }

    public function testPoolInitialStateWithNoRuns()
    {
        $pool = new Pool();

        $this->assertFalse($pool->isSuccessful(), 'should not be successful');
        $this->assertFalse($pool->isRunning(), 'should not be running');
        $this->assertFalse($pool->hasStarted(), 'should not be started');
    }

    public function testPoolAddingRun()
    {
        $pool = new Pool();
        $pool->add(new CallbackRun(function () {
            return true;
        }));

        $this->assertEquals(1, $pool->count());
    }

    public function testPoolAddingRunFiresAnEvent()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $hit = false;

        $pool = new Pool();
        $pool->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) use ($pool, $run, &$hit) {
                $this->assertSame($pool, $event->getPool());
                $this->assertSame($run, $event->getRun());
                $hit = true;
            }
        );

        $pool->add($run);

        $this->assertEquals(1, $pool->count());
        $this->assertTrue($hit);
    }

    public function testPoolAddingProcess()
    {
        $pool = new Pool();
        $pool->add($this->process);

        $this->assertEquals(1, $pool->count());
        $runs = $pool->getAll();
        $run = reset($runs);

        $this->assertEquals($this->process, $run->getProcess());
    }

    public function testPoolAddingProcessFiresAnEvent()
    {
        $pool = new Pool();
        $pool->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) use ($pool, &$hit) {
                $this->assertSame($pool, $event->getPool());
                $run = $event->getRun();
                if ($run instanceof ProcessRun) {
                    $this->assertSame($this->process, $run->getProcess());
                }
                $hit = true;
            }
        );

        $pool->add($this->process);

        $this->assertEquals(1, $pool->count());
        $runs = $pool->getAll();
        $run = reset($runs);

        $this->assertEquals($this->process, $run->getProcess());
        $this->assertTrue($hit);
    }

    public function testAddingACompletedRunWillAddItToTheFinishedListAndStartThePool()
    {
        $run = new CallbackRun(function () {
            return true;
        });
        $run->start();

        $pool = new Pool();
        $hit = false;
        $pool->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) use (&$hit, $pool) {
                $hit = true;
                $this->assertSame($pool, $event->getRun());
            }
        );

        $this->assertFalse($hit);
        $pool->add($run);
        $this->assertTrue($hit);

        $this->assertCount(1, $pool->getFinished());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->hasStarted());
    }

    public function testSuccessfulRun()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $pool = new Pool([$run]);
        $pool->run(0);

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());
    }

    public function testSuccessfulRunWithEvents()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $pool = new Pool([$run]);

        $startedHit = false;
        $completedHit = false;

        $pool->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) use ($pool, &$startedHit) {
                $this->assertSame($pool, $event->getRun());
                $startedHit = true;
            }
        );
        $pool->addListener(
            RunEvent::COMPLETED,
            function (RunEvent $event) use ($pool, &$completedHit) {
                $this->assertSame($pool, $event->getRun());
                $completedHit = true;
            }
        );

        $pool->run(0);

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());

        $this->assertTrue($startedHit);
        $this->assertTrue($completedHit);
    }

    public function testFailedRunWithEvents()
    {
        $exception = new \RuntimeException('bwark');
        $run = new CallbackRun(function () use ($exception) {
            throw $exception;
        });

        $pool = new Pool([$run]);

        $failedHit = false;

        $pool->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use ($pool, &$failedHit) {
                $this->assertSame($pool, $event->getRun());
                $failedHit = true;
            }
        );

        $pool->run(0);

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertFalse($pool->isSuccessful());

        $this->assertTrue($failedHit);

        $this->assertEquals([$exception], $pool->getExceptions());
    }

    public function testPoolAbleToAddRunningProcessWhenPoolHasStarted()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')
                ->andReturn(false, false, false, true); // add to pool, check start, check start, started
        $process->shouldReceive('isRunning')->andReturn(false, true, false);
        $process->shouldReceive('start')->atLeast()->once();

        $pool = new Pool([$process]);
        $pool->start();

        $process2 = Mockery::mock(Process::class);
        $process2->shouldReceive('stop');
        $process2->shouldReceive('isStarted')->andReturn(true);
        $process2->shouldReceive('isRunning')->andReturn(true, false);
        $pool->add($process2);

        $this->assertEquals(2, $pool->count());
        $this->assertTrue($pool->isRunning());
    }

    public function testOnProgressIsCalledDuringProcessRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true);
        $process->shouldReceive('isRunning')->andReturn(false, true, false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $hit = false;

        $pool = new Pool();
        $pool->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use ($pool, &$hit) {
                $run = $event->getRun();
                $this->assertEquals($pool, $run);
                $hit = true;
            }
        );

        $pool->add($process);
        $pool->run(0);
        $this->assertTrue($hit);
    }

    public function testFinished()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $pool = new Pool([$run]);
        $pool->run(0);

        $this->assertEquals([$run], $pool->getFinished());
    }

    public function testTags()
    {
        $pool = new Pool([], ['tag1', 'key' => 'value']);

        $this->assertSame(['tag1', 'key' => 'value'], $pool->getTags());
    }

    public function testProgress()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $pool = new Pool([$run]);

        $this->assertEquals([0, 1, 0], $pool->getProgress());
        $pool->run(0);

        $this->assertEquals([1, 1, 1], $pool->getProgress());
    }

    public function testDuration()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $pool = new Pool([$run]);

        $this->assertEquals(0, $pool->getDuration());
        $pool->start();

        $this->assertGreaterThan(0, $pool->getDuration());
        $pool->run(0);
    }

    public function testPriority()
    {
        $pool = new Pool();
        $this->assertEquals(1, $pool->getPriority());
        $this->assertSame($pool, $pool->setPriority(2));
        $this->assertEquals(2, $pool->getPriority());
    }
}
