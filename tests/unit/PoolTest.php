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

use Exception;
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
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false, 'addListener' => $run, 'getPriority' => 1.0]);

        $pool = new Pool();
        $pool->add($run);

        $this->assertEquals(1, $pool->count());
    }

    public function testPoolAddingRunFiresAnEvent()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false, 'addListener' => $run, 'getPriority' => 1.0]);

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
        $run->shouldReceive('getPriority')
            ->andReturn(1.0);
        $run->allows()
            ->addListener(RunEvent::FAILED, Mockery::type('callable'));

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
            ->andReturn(false, false, true); // add, start, run
        $run->shouldReceive('isSuccessful')
            ->andReturn(true);
        $run->shouldReceive('getPriority')
            ->andReturn(1.0);
        $run->allows()
            ->addListener(RunEvent::FAILED, Mockery::type('callable'));

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

    public function testFailedRunWithEvents()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->allows([
            'isRunning'    => false,
            'isSuccessful' => false,
            'getPriority'  => 1.0,
        ]);
        $run->allows()
            ->hasStarted()
            ->andReturns(false, false, true);
        $run->shouldReceive('poll')
            ->andReturn(true, false);
        $failedListener = null;
        $run->allows()
            ->addListener(
                RunEvent::FAILED,
                Mockery::on(function (callable $handler) use (&$failedListener) {
                    $failedListener = $handler;
                    return true;
                })
            );

        $pool = new Pool([$run]);

        $startedHit = false;
        $failedHit = false;

        $pool->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) use ($pool, &$startedHit) {
                $this->assertSame($pool, $event->getRun());
                $startedHit = true;
            }
        );
        $pool->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use ($pool, &$failedHit) {
                $this->assertSame($pool, $event->getRun());
                $failedHit = true;
            }
        );

        $run->shouldReceive('start');
        $pool->run(0);

        $exception = new Exception('test exception');
        $run->allows()
            ->getExceptions()->andReturn([$exception]);

        call_user_func($failedListener, new RunEvent($run));

        $this->assertTrue($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertFalse($pool->isSuccessful());

        $this->assertTrue($startedHit);
        $this->assertTrue($failedHit);

        $this->assertEquals([$exception], $pool->getExceptions());
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
                      ->allows(['isRunning' => false, 'hasStarted' => false, 'start' => null, 'addListener' => true, 'getPriority' => 1.0]);
        $pool = new Pool([$run]);
        $pool->start();

        $run2 = Mockery::mock(RunInterface::class)
                       ->allows(['isRunning' => true, 'start' => null, 'addListener' => true, 'getPriority' => 1.0]);
        $pool->add($run2);

        $this->assertEquals(2, $pool->count());
        $this->assertTrue($pool->isRunning());
    }

    public function testOnProgressIsCalledDuringProcessRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(false, false, true);
        $process->shouldReceive('isRunning')->andReturn(false, false, true, false);
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
                          'isSuccessful' => true,
                          'addListener'  => true,
                          'getPriority'  => 1.0,
                      ]);
        $run->allows()
            ->hasStarted()
            ->andReturns(false, true);

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
                          'isSuccessful' => true,
                          'addListener'  => true,
                          'getPriority'  => 1.0,
                      ]);
        $run->allows()
            ->hasStarted()
            ->andReturns(false, true);

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
                          'addListener'  => true,
                          'getPriority'  => 1.0,
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

    public function testFinished()
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
        $run->allows()
            ->addListener(RunEvent::FAILED, Mockery::type('callable'));

        $pool = new Pool([$run]);

        $run->shouldReceive('start');
        $pool->run(0);

        $this->assertEquals([$run], $pool->getFinished());
    }

    public function testTags()
    {
        $pool = new Pool([], Pool::NO_MAX, false, ['tag1', 'key' => 'value']);

        $this->assertSame(['tag1', 'key' => 'value'], $pool->getTags());
    }

    public function testProgress()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')
            ->andReturn(false);
        $run->shouldReceive('poll')
            ->andReturn(true, false);
        $run->shouldReceive('hasStarted')
            ->andReturn(false, false, true);
        $run->shouldReceive('isSuccessful')
            ->andReturn(true);
        $run->shouldReceive('getPriority')
            ->andReturn(1.0);
        $run->allows()
            ->addListener(RunEvent::FAILED, Mockery::type('callable'));

        $pool = new Pool([$run]);

        $this->assertEquals([0, 1, 0], $pool->getProgress());

        $run->shouldReceive('start');
        $pool->run(0);

        $this->assertEquals([1, 1, 1], $pool->getProgress());
    }

    public function testDuration()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')->andReturn(false, true, false);
        $run->shouldReceive('poll')->andReturn(true, false);
        $run->shouldReceive('hasStarted')->andReturn(false, false, true);
        $run->shouldReceive('isSuccessful')->andReturn(true);
        $run->shouldReceive('getPriority')->andReturn(1.0);
        $run->allows()->addListener(RunEvent::FAILED, Mockery::type('callable'));

        $pool = new Pool([$run]);

        $this->assertEquals(0, $pool->getDuration());

        $run->shouldReceive('start');

        $pool->start();

        $this->assertGreaterThan(0, $pool->getDuration());

        $pool->run(0);

        $duration = $pool->getDuration();

        $this->assertEquals($duration, $pool->getDuration());
    }

    public function testPriorityWillRunTheHighestFirst()
    {
        $pool = new Pool();
        $pool->setRunInstantly(false)
             ->setMaxSimultaneous(1);

        $run1 = Mockery::mock(RunInterface::class)
                       ->allows([
                           'start'       => null,
                           'poll'        => false,
                           'addListener' => true,
                           'getPriority' => '1.5',
                       ]);
        $run1->allows()->hasStarted()->andReturns(false, false, true);
        $run1->allows()->isRunning()->andReturns(false, true, false);
        $run1->allows()->isSuccessful()->andReturns(false, true);

        $pool->add($run1);

        $run2 = Mockery::mock(RunInterface::class)
                       ->allows([
                           'start'       => null,
                           'poll'        => false,
                           'addListener' => true,
                           'getPriority' => '1.6',
                       ]);
        $run2->allows()->hasStarted()->andReturns(false, false, true);
        $run2->allows()->isRunning()->andReturns(false, true, false);
        $run2->allows()->isSuccessful()->andReturns(false, true);

        $pool->add($run2);

        $this->assertFalse($pool->hasStarted());
        $this->assertFalse($pool->isRunning());
        $this->assertFalse($pool->isSuccessful());

        $pool->poll();

        $this->assertTrue($pool->hasStarted());
        $this->assertTrue($pool->isRunning());
        $this->assertFalse($pool->isSuccessful());

        $waiting = $pool->getWaiting();

        $this->assertSame($run1, reset($waiting));

        $pool->poll();

        $this->assertTrue($pool->hasStarted());
        $this->assertTrue($pool->isRunning());
        $this->assertTrue($pool->isSuccessful());

        $pool->poll();
    }
}
