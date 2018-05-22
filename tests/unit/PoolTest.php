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
        $pool->add(/** @scrutinizer ignore-type */
            $nope);
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

    public function testPoolAddingProcess()
    {
        $pool = new Pool();
        $pool->add($this->process);

        $this->assertEquals(1, $pool->count());
        $runs = $pool->getAll();
        $run = reset($runs);

        $this->assertEquals($this->process, $run->getProcess());
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

    public function testOnSuccessIsCalledOnSuccessfulProcess()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $hit = false;

        $pool = new Pool(
            [],
            function ($proc) use ($process, &$hit) {
                $this->assertSame($proc, $process);
                $hit = true;
            }
        );

        $pool->add($process);

        $pool->run(0);

        $this->assertTrue($hit);
    }

    public function testOnFailureIsCalledForErroredProcess()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(false);

        $hit = false;

        $pool = new Pool(
            [],
            null,
            function ($proc) use ($process, &$hit) {
                $this->assertEquals($proc, $process);
                $hit = true;
            }
        );

        $pool->add($process);

        $pool->run(0);

        $this->assertTrue($hit);
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

        $pool = new Pool(
            [],
            null,
            null,
            function ($proc) use ($process, &$hit) {
                $this->assertEquals($proc, $process);
                $hit = true;
            }
        );

        $pool->add($process);

        $pool->run(0);

        $this->assertTrue($hit);
    }

    public function testOnSuccessSetterIsCalledOnSuccessfulProcess()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $hit = false;

        $pool = new Pool();
        $this->assertSame(
            $pool,
            $pool->setOnSuccess(function ($proc) use ($process, &$hit) {
                $this->assertEquals($proc, $process);
                $hit = true;
            })
        );

        $pool->add($process);

        $pool->run(0);

        $this->assertTrue($hit);
    }

    public function testOnFailureSetterIsCalledForErroredProcess()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false);
        $process->shouldReceive('poll')->andReturn(false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(false);

        $hit = false;

        $pool = new Pool();
        $this->assertSame(
            $pool,
            $pool->setOnFailure(function ($proc) use ($process, &$hit) {
                $this->assertEquals($proc, $process);
                $hit = true;
            })
        );

        $pool->add($process);

        $pool->run(0);

        $this->assertTrue($hit);
    }

    public function testOnProgressSetterIsCalledDuringProcessRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false, false, true, false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(false);

        $hit = false;

        $pool = new Pool();
        $this->assertSame(
            $pool,
            $pool->setOnProgress(function ($proc) use ($process, &$hit) {
                $this->assertEquals($proc, $process);
                $hit = true;
            })
        );

        $pool->add($process);

        $pool->run(0);

        $this->assertTrue($hit);
    }

    public function testOnStartSetterIsCalledDuringProcessRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(true);
        $process->shouldReceive('isRunning')->andReturn(false, false, true, false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(false);

        $hit = false;

        $pool = new Pool();
        $this->assertSame(
            $pool,
            $pool->setOnStart(function ($proc) use ($process, &$hit) {
                $this->assertEquals($proc, $process);
                $hit = true;
            })
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

        $pool = new Pool([$run], null, null, null, null, Pool::NO_MAX, true);

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
        $run = Mockery::mock(RunInterface::class);
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
        $run = Mockery::mock(RunInterface::class);
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
