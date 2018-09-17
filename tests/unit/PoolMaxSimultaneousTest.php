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

use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

class PoolMaxSimultaneousTest extends TestCase
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

    public function testProperties()
    {
        $pool = new PriorityPool();

        $this->assertEquals(-1, $pool->getMaxSimultaneous());
        $this->assertSame($pool, $pool->setMaxSimultaneous(1));
        $this->assertEquals(1, $pool->getMaxSimultaneous());
    }

    public function testSingleMaxAdding2Processes()
    {
        $pool = new PriorityPool();
        $this->assertSame($pool, $pool->setMaxSimultaneous(1));

        $this->assertEquals(1, $pool->getMaxSimultaneous());

        $process1 = Mockery::mock(Process::class);
        $process1->shouldReceive('stop');
        $process1->shouldReceive('isStarted')->andReturn(false, false, false, true); //add, add2, start, check
        $process1->shouldReceive('isRunning')->andReturn(false); //add, add2, check

        $pool->add($process1);

        $process2 = Mockery::mock(Process::class);
        $process2->shouldReceive('stop');
        $process2->shouldReceive('isStarted')->andReturn(false, false, false, true); //add, add2, start, check
        $process2->shouldReceive('isRunning')->andReturn(false); //add, add2, check

        $pool->add($process2);

        $process1->shouldReceive('start')->once();
        $pool->start();

        $this->assertCount(1, $pool->getRunning());
        $this->assertCount(1, $pool->getWaiting());

        $running = $pool->getRunning();
        $run = reset($running);
        $this->assertInstanceOf(ProcessRun::class, $run);
        $this->assertSame($process1, $run->getProcess(), 'first process added should be run first');

        $waiting = $pool->getWaiting();
        $run = reset($waiting);
        $this->assertInstanceOf(ProcessRun::class, $run);
        $this->assertSame($process2, $run->getProcess(), 'second process added should be waiting');

        $process1->shouldReceive('isSuccessful')->andReturn(true);
        $process2->shouldReceive('start');

        $this->assertTrue($pool->poll()); // check running state

        $this->assertCount(1, $pool->getRunning());
        $this->assertCount(0, $pool->getWaiting());

        $running = $pool->getRunning();
        $run = reset($running);
        $this->assertInstanceOf(ProcessRun::class, $run);
        $this->assertSame($process2, $run->getProcess(), 'second process added should now be running');

        $process2->shouldReceive('isSuccessful')->andReturn(true);
        $process2->shouldReceive('isSuccessful')->andReturn(true);
        $this->assertFalse($pool->poll());

        $this->assertCount(0, $pool->getRunning());
        $this->assertCount(0, $pool->getWaiting());
    }

    public function testAddingTooManyProcessesPutsThemOnTheWaitingList()
    {
        $pool = new PriorityPool([], 1);
        $this->assertEquals(1, $pool->getMaxSimultaneous());

        $pool->add($this->process);

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(false);
        $process->shouldReceive('isRunning')->andReturn(false);

        $this->process->shouldReceive('start');

        $pool->start();

        $this->assertCount(1, $pool->getRunning());
        $this->assertCount(0, $pool->getWaiting());

        $pool->add($process);

        $this->assertCount(1, $pool->getRunning());
        $this->assertCount(1, $pool->getWaiting());
    }
}
