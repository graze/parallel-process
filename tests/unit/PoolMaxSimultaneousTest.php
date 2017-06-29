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

use Graze\ParallelProcess\Pool;
use Graze\ParallelProcess\Run;
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
        $pool = new Pool();

        $this->assertEquals(-1, $pool->getMaxSimultaneous());
        $this->assertSame($pool, $pool->setMaxSimultaneous(1));
        $this->assertEquals(1, $pool->getMaxSimultaneous());
    }

    public function testSingleMaxAdding2Processes()
    {
        $pool = new Pool();
        $this->assertSame($pool, $pool->setMaxSimultaneous(1));

        $this->assertEquals(1, $pool->getMaxSimultaneous());

        $pool->add($this->process);

        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(false);
        $process->shouldReceive('isRunning')->andReturn(false);

        $pool->add($process);

        $this->process->shouldReceive('start');

        $pool->start();

        $this->assertCount(1, $pool->getRunning());
        $this->assertCount(1, $pool->getWaiting());

        $running = $pool->getRunning();
        $run = reset($running);
        $this->assertInstanceOf(Run::class, $run);
        $this->assertSame($this->process, $run->getProcess(), 'first process added should be run first');

        $waiting = $pool->getWaiting();
        $run = reset($waiting);
        $this->assertInstanceOf(Run::class, $run);
        $this->assertSame($process, $run->getProcess(), 'second process added should be waiting');

        $process->shouldReceive('start');

        $this->assertTrue($pool->poll()); // check running state

        $this->assertCount(1, $pool->getRunning());
        $this->assertCount(0, $pool->getWaiting());

        $running = $pool->getRunning();
        $run = reset($running);
        $this->assertInstanceOf(Run::class, $run);
        $this->assertSame($process, $run->getProcess(), 'second process added should now be running');

        $this->assertFalse($pool->poll());

        $this->assertCount(0, $pool->getRunning());
        $this->assertCount(0, $pool->getWaiting());
    }

    public function testAddingTooManyProcessesPutsThemOnTheWaitingList()
    {
        $pool = new Pool([], null, null, null, 1);
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
