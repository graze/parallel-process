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

namespace Graze\ParallelProcess\Test\Unit;

use Graze\DataStructure\Collection\CollectionInterface;
use Graze\ParallelProcess\Event\PoolRunEvent;
use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\Run;
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

    public function testPoolIsACollectionOfRuns()
    {
        $pool = new Pool();
        $this->assertInstanceOf(CollectionInterface::class, $pool);

        $this->assertSame($pool, $pool->add($this->process));

        $runs = $pool->getAll();
        $this->assertCount(1, $runs);

        $this->assertInstanceOf(Run::class, reset($runs));
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
                             ->allows(['isRunning' => false, 'hasStarted' => false]);
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
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false]);

        $pool = new Pool();
        $pool->add($run);

        $this->assertEquals(1, $pool->count());
    }

    public function testPoolAddingRunFiresAnEvent()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false]);

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
                if ($run instanceof Run) {
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

    public function testSuccessfulRun()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')
            ->andReturn(false);
        $run->shouldReceive('poll')
            ->andReturn(true, false);
        $run->shouldReceive('hasStarted')
            ->andReturn(true);
        $run->shouldReceive('isSuccessful')
            ->andReturn(true);

        $pool = new Pool([$run]);

        $run->shouldReceive('start');
        $pool->run(0);

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());
    }

    public function testSuccessfulRunWithEvents()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')
            ->andReturn(false);
        $run->shouldReceive('poll')
            ->andReturn(true, false);
        $run->shouldReceive('hasStarted')
            ->andReturn(true);
        $run->shouldReceive('isSuccessful')
            ->andReturn(true);

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

        $run->shouldReceive('start');
        $pool->run(0);

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());

        $this->assertTrue($startedHit);
        $this->assertTrue($completedHit);
    }

    /**
     * @expectedException \Graze\ParallelProcess\Exceptions\NotRunningException
     */
    public function testPoolUnableToAddRunningProcessWhenPoolHasNotStarted()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')
            ->andReturn(true);

        $pool = new Pool();

        $pool->add($run);
    }

    public function testPoolAbleToAddRunningProcessWhenPoolHasStarted()
    {
        $run = Mockery::mock(RunInterface::class)
                      ->allows(['isRunning' => false, 'hasStarted' => false, 'start' => null]);
        $pool = new Pool([$run]);
        $pool->start();

        $run2 = Mockery::mock(RunInterface::class)
                       ->allows(['isRunning' => true, 'start' => null]);
        $pool->add($run2);

        $this->assertEquals(2, $pool->count());
        $this->assertTrue($pool->isRunning());
    }

    public function testOnProgressIsCalledDuringProcessRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false, false, true, false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(false);

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

    public function testPoolInstantRunStates()
    {
        $pool = new Pool();

        $this->assertFalse($pool->isRunInstantly());
        $this->assertSame($pool, $pool->setRunInstantly(true));
        $this->assertTrue($pool->isRunInstantly());
    }

    public function testPoolInitialStateWithInstantRun()
    {
        $run = Mockery::mock(RunInterface::class)
                      ->allows([
                          'start'        => null,
                          'isRunning'    => false,
                          'poll'         => false,
                          'hasStarted'   => true,
                          'isSuccessful' => true,
                      ]);

        $pool = new Pool([$run], Pool::NO_MAX, true);

        $this->assertTrue($pool->hasStarted());
        $this->assertTrue($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());
        $this->assertTrue($pool->isRunInstantly());

        $pool->poll();

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());
    }

    public function testPoolRunsRunWhenInstantRunIsOn()
    {
        $pool = new Pool();
        $pool->setRunInstantly(true);

        $run = Mockery::mock(RunInterface::class)
                      ->allows([
                          'start'        => null,
                          'isRunning'    => false,
                          'poll'         => false,
                          'hasStarted'   => true,
                          'isSuccessful' => true,
                      ]);

        $pool->add($run);

        $this->assertTrue($pool->hasStarted());
        $this->assertTrue($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());

        $pool->poll();

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());
    }

    public function testRunningRunAddingToAnInstantRunProcessCarriesOn()
    {
        $pool = new Pool();
        $pool->setRunInstantly(true);

        $run = Mockery::mock(RunInterface::class)
                      ->allows([
                          'start'        => null,
                          'isRunning'    => true,
                          'poll'         => false,
                          'hasStarted'   => true,
                          'isSuccessful' => true,
                      ]);

        $pool->add($run);

        $this->assertTrue($pool->hasStarted());
        $this->assertTrue($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());

        $pool->poll();

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());
    }
}
