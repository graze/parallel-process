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

        $this->process = Mockery::mock(Process::class);
        $this->process->shouldReceive('stop');
        $this->process->shouldReceive('isStarted')->andReturn(false);
        $this->process->shouldReceive('isRunning')->andReturn(false);
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
                             ->shouldReceive('isRunning')
                             ->andReturn(false)
                             ->getMock()
                             ->shouldReceive('hasStarted')
                             ->andReturn(false)
                             ->getMock();
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
        $run->shouldReceive('hasStarted')
            ->andReturn(false);
        $run->shouldReceive('isRunning')
            ->andReturn(false);
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
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')
            ->andReturn(false);
        $run->shouldReceive('hasStarted')
            ->andReturn(false);
        $run->shouldReceive('start');
        $pool = new Pool([$run]);
        $pool->start();

        $run2 = Mockery::mock(RunInterface::class);
        $run2->shouldReceive('isRunning')
             ->andReturn(true);
        $run2->shouldReceive('start');
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
}
