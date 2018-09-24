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
use Graze\ParallelProcess\PoolInterface;
use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

class PriorityPoolTest extends TestCase
{
    /** @var mixed */
    private $process;

    public function setUp()
    {
        parent::setUp();

        $this->process = Mockery::mock(Process::class)
                                ->allows(['stop' => null, 'isStarted' => false, 'isRunning' => false]);
    }

    public function testPriorityPoolIsARunInterface()
    {
        $priorityPool = new PriorityPool();
        $this->assertInstanceOf(RunInterface::class, $priorityPool);
    }

    public function testPriorityPoolIsACollectionOfRuns()
    {
        $priorityPool = new PriorityPool();
        $this->assertInstanceOf(CollectionInterface::class, $priorityPool);

        $this->assertSame($priorityPool, $priorityPool->add($this->process));

        $runs = $priorityPool->getAll();
        $this->assertCount(1, $runs);

        $this->assertInstanceOf(ProcessRun::class, reset($runs));
    }

    public function testPriorityPoolInitialStateWithProcess()
    {
        $priorityPool = new PriorityPool();
        $priorityPool->add($this->process);

        $this->assertFalse($priorityPool->isSuccessful());
        $this->assertFalse($priorityPool->isRunning());
        $this->assertFalse($priorityPool->hasStarted());
        $this->assertFalse($priorityPool->isRunInstantly());
        $this->assertEquals(PriorityPool::NO_MAX, $priorityPool->getMaxSimultaneous());
    }

    public function testPriorityPoolConstructor()
    {
        $runs = [];
        for ($i = 0; $i < 2; $i++) {
            $runs[] = Mockery::mock(RunInterface::class)
                             ->allows(['isRunning' => false, 'hasStarted' => false, 'addListener' => true, 'getPriority' => 1.0]);
        }

        $priorityPool = new PriorityPool($runs);

        $this->assertEquals(2, $priorityPool->count());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddingNonRunInterfaceWillThrowException()
    {
        $nope = Mockery::mock();
        $priorityPool = new PriorityPool();
        $priorityPool->add($nope);
    }

    public function testPriorityPoolInitialStateWithNoRuns()
    {
        $priorityPool = new PriorityPool();

        $this->assertFalse($priorityPool->isSuccessful(), 'should not be successful');
        $this->assertFalse($priorityPool->isRunning(), 'should not be running');
        $this->assertFalse($priorityPool->hasStarted(), 'should not be started');
    }

    public function testPriorityPoolAddingRun()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false, 'addListener' => $run, 'getPriority' => 1.0]);

        $priorityPool = new PriorityPool();
        $priorityPool->add($run);

        $this->assertEquals(1, $priorityPool->count());
    }

    public function testPriorityPoolAddingRunFiresAnEvent()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false, 'addListener' => $run, 'getPriority' => 1.0]);

        $hit = false;

        $priorityPool = new PriorityPool();
        $priorityPool->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) use ($priorityPool, $run, &$hit) {
                $this->assertSame($priorityPool, $event->getPool());
                $this->assertSame($run, $event->getRun());
                $hit = true;
            }
        );
        $priorityPool->add($run);

        $this->assertEquals(1, $priorityPool->count());
        $this->assertTrue($hit);
    }

    public function testPriorityPoolAddingProcess()
    {
        $priorityPool = new PriorityPool();
        $priorityPool->add($this->process);

        $this->assertEquals(1, $priorityPool->count());
        $runs = $priorityPool->getAll();
        $run = reset($runs);

        $this->assertEquals($this->process, $run->getProcess());
    }

    public function testPriorityPoolAddingProcessFiresAnEvent()
    {
        $priorityPool = new PriorityPool();
        $priorityPool->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) use ($priorityPool, &$hit) {
                $this->assertSame($priorityPool, $event->getPool());
                $run = $event->getRun();
                if ($run instanceof ProcessRun) {
                    $this->assertSame($this->process, $run->getProcess());
                }
                $hit = true;
            }
        );
        $priorityPool->add($this->process);

        $this->assertEquals(1, $priorityPool->count());
        $runs = $priorityPool->getAll();
        $run = reset($runs);

        $this->assertEquals($this->process, $run->getProcess());
        $this->assertTrue($hit);
    }

    public function testAddingChildPoolWillAddAllTheChildrenIntoThisPoolAndCreateAListener()
    {
        $run = new CallbackRun(function () {
            return true;
        });
        $pool = Mockery::mock(PoolInterface::class, RunInterface::class);
        $pool->allows([
            'isRunning'   => false,
            'hasStarted'  => false,
            'getPriority' => 1,
            'getAll'      => [$run],
        ]);

        $callback = null;
        $pool->allows()
             ->addListener(
                 PoolRunEvent::POOL_RUN_ADDED,
                 Mockery::on(function (callable $func) use (&$callback) {
                     $callback = $func;
                     return true;
                 })
             )
             ->once();

        $priorityPool = new PriorityPool();
        $priorityPool->add($pool);

        $this->assertCount(1, $priorityPool->getAll());
        $this->assertEquals([$run], $priorityPool->getAll());

        $run2 = new CallbackRun(function () {
            return true;
        });

        call_user_func($callback, new PoolRunEvent($pool, $run2));

        $this->assertCount(2, $priorityPool->getAll());
        $this->assertEquals([$run, $run2], $priorityPool->getAll());
    }

    /**
     * @expectedException \Graze\ParallelProcess\Exceptions\NotRunningException
     */
    public function testPriorityPoolUnableToAddRunningProcessWhenPoolHasNotStarted()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')
            ->andReturn(true);

        $priorityPool = new PriorityPool();

        $priorityPool->add($run);
    }

    public function testPriorityPoolInstantRunStates()
    {
        $priorityPool = new PriorityPool();

        $this->assertFalse($priorityPool->isRunInstantly());
        $this->assertSame($priorityPool, $priorityPool->setRunInstantly(true));
        $this->assertTrue($priorityPool->isRunInstantly());
    }

    public function testPriorityPoolInitialStateWithInstantRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true);
        $process->shouldReceive('isRunning')->andReturn(false, false, true, false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $priorityPool = new PriorityPool([$process], PriorityPool::NO_MAX, true);

        $this->assertTrue($priorityPool->hasStarted());
        $this->assertTrue($priorityPool->isRunning());
        $this->assertFalse($priorityPool->isSuccessful());
        $this->assertTrue($priorityPool->isRunInstantly());

        $priorityPool->poll();

        $this->assertTrue($priorityPool->hasStarted());
        $this->assertFalse($priorityPool->isRunning());
        $this->assertTrue($priorityPool->isSuccessful());
    }

    public function testPriorityPoolRunsRunWhenInstantRunIsOn()
    {
        $priorityPool = new PriorityPool();
        $priorityPool->setRunInstantly(true);

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(false, false, false, true);
        $process->shouldReceive('isRunning')->andReturn(false, false, true, false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $this->assertFalse($priorityPool->hasStarted());
        $this->assertFalse($priorityPool->isRunning());
        $this->assertFalse($priorityPool->isSuccessful());

        $priorityPool->add($process);

        $this->assertTrue($priorityPool->hasStarted());
        $this->assertTrue($priorityPool->isRunning());
        $this->assertFalse($priorityPool->isSuccessful());

        $priorityPool->poll();

        $this->assertTrue($priorityPool->hasStarted());
        $this->assertFalse($priorityPool->isRunning());
        $this->assertTrue($priorityPool->isSuccessful());
    }

    public function testPriorityWillRunTheHighestFirst()
    {
        $priorityPool = new PriorityPool();
        $priorityPool->setRunInstantly(false)
                     ->setMaxSimultaneous(1);

        $run1 = new CallbackRun(
            function () {
                return true;
            },
            [],
            1.5
        );

        $priorityPool->add($run1);

        $run2 = new CallbackRun(
            function () {
                return true;
            },
            [],
            1.6
        );

        $priorityPool->add($run2);

        $this->assertFalse($priorityPool->hasStarted());
        $this->assertFalse($priorityPool->isRunning());
        $this->assertFalse($priorityPool->isSuccessful());

        $priorityPool->poll();

        $this->assertTrue($priorityPool->hasStarted());
        $this->assertTrue($priorityPool->isRunning());
        $this->assertFalse($priorityPool->isSuccessful());

        $waiting = $priorityPool->getWaiting();

        $this->assertSame($run1, reset($waiting));

        $priorityPool->poll();

        $this->assertTrue($priorityPool->hasStarted());
        $this->assertFalse($priorityPool->isRunning());
        $this->assertTrue($priorityPool->isSuccessful());

        $priorityPool->poll();
    }

    public function testModifyingThePriorityWillChangeWhichRunStartsFirst()
    {
        $pool = new PriorityPool();
        $pool->setMaxSimultaneous(1);

        $run = new CallbackRun(
            function () {
                return true;
            },
            [],
            1.1
        );
        $run2 = new CallbackRun(
            function () {
                return true;
            },
            [],
            1.2
        );

        $pool->add($run);
        $pool->add($run2);

        $run->setPriority(1.3);

        $pool->start();

        $this->assertTrue($run->hasStarted());
        $this->assertFalse($run2->hasStarted());

        $run3 = new CallbackRun(
            function () {
                return true;
            },
            [],
            1.05
        );
        $pool->add($run3);

        $run->setPriority(1.00);

        $this->assertTrue($run->hasStarted());
        $this->assertFalse($run2->hasStarted());
        $this->assertFalse($run3->hasStarted());

        $pool->poll();

        $this->assertTrue($run->hasStarted());
        $this->assertTrue($run2->hasStarted());
        $this->assertFalse($run3->hasStarted());
    }
}
