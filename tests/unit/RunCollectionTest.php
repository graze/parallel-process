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
use Graze\ParallelProcess\Run;
use Graze\ParallelProcess\RunCollection;
use Graze\ParallelProcess\RunInterface;
use Graze\ParallelProcess\Test\TestCase;
use Mockery;
use Symfony\Component\Process\Process;

class RunCollectionTest extends TestCase
{
    /** @var mixed */
    private $process;
    /** @var mixed */
    private $parentPool;

    public function setUp()
    {
        parent::setUp();

        $this->parentPool = Mockery::mock(PoolInterface::class);
        $this->process = Mockery::mock(Process::class)
                                ->allows(['stop' => null, 'isStarted' => false, 'isRunning' => false]);
    }

    public function testRunCollectionIsARunInterface()
    {
        $collection = new RunCollection($this->parentPool);
        $this->assertInstanceOf(RunInterface::class, $collection);
    }

    public function testRunCollectionIsAPoolInterface()
    {
        $collection = new RunCollection($this->parentPool);
        $this->assertInstanceOf(PoolInterface::class, $collection);
    }

    public function testRunCollectionIsACollectionOfRuns()
    {
        $collection = new RunCollection($this->parentPool);
        $this->assertInstanceOf(CollectionInterface::class, $collection);

        $this->parentPool->allows()
                         ->add(Mockery::type(Run::class))
                         ->once();
        $this->assertSame($collection, $collection->add($this->process));

        $runs = $collection->getAll();
        $this->assertCount(1, $runs);

        $this->assertInstanceOf(Run::class, reset($runs));
    }

    public function testRunCollectionInitialStateWithProcess()
    {
        $collection = new RunCollection($this->parentPool);

        $this->parentPool->allows()
                         ->add(Mockery::type(Run::class))
                         ->once();
        $collection->add($this->process);

        $this->assertFalse($collection->isSuccessful());
        $this->assertFalse($collection->isRunning());
        $this->assertFalse($collection->hasStarted());
    }

    public function testRunCollectionConstructor()
    {
        $runs = [];
        for ($i = 0; $i < 2; $i++) {
            $runs[] = Mockery::mock(RunInterface::class)
                             ->allows(['isRunning' => false, 'hasStarted' => false, 'addListener' => true, 'getPriority' => 1.0]);
        }

        $collection = new Pool($runs);

        $this->assertEquals(2, $collection->count());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddingNonRunInterfaceWillThrowException()
    {
        $nope = Mockery::mock();
        $collection = new RunCollection($this->parentPool);
        $collection->add($nope);
    }

    public function testRunCollectionInitialStateWithNoRuns()
    {
        $collection = new RunCollection($this->parentPool);

        $this->assertFalse($collection->isSuccessful(), 'should not be successful');
        $this->assertFalse($collection->isRunning(), 'should not be running');
        $this->assertFalse($collection->hasStarted(), 'should not be started');
    }

    public function testRunCollectionAddingRun()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false, 'addListener' => $run]);

        $collection = new RunCollection($this->parentPool);
        $this->parentPool->allows()
                         ->add($run)
                         ->once();
        $collection->add($run);

        $this->assertEquals(1, $collection->count());
    }

    public function testRunCollectionAddingRunFiresAnEvent()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->allows(['hasStarted' => false, 'isRunning' => false, 'addListener' => $run]);

        $hit = false;

        $collection = new RunCollection($this->parentPool);
        $collection->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) use ($collection, $run, &$hit) {
                $this->assertSame($collection, $event->getPool());
                $this->assertSame($run, $event->getRun());
                $hit = true;
            }
        );

        $this->parentPool->allows()
                         ->add($run)
                         ->once();

        $collection->add($run);

        $this->assertEquals(1, $collection->count());
        $this->assertTrue($hit);
    }

    public function testRunCollectionAddingProcess()
    {
        $collection = new RunCollection($this->parentPool);
        $this->parentPool->allows()
                         ->add(Mockery::type(RunInterface::class))
                         ->once();
        $collection->add($this->process);

        $this->assertEquals(1, $collection->count());
        $runs = $collection->getAll();
        $run = reset($runs);

        $this->assertEquals($this->process, $run->getProcess());
    }

    public function testRunCollectionAddingProcessFiresAnEvent()
    {
        $collection = new RunCollection($this->parentPool);
        $collection->addListener(
            PoolRunEvent::POOL_RUN_ADDED,
            function (PoolRunEvent $event) use ($collection, &$hit) {
                $this->assertSame($collection, $event->getPool());
                $run = $event->getRun();
                if ($run instanceof Run) {
                    $this->assertSame($this->process, $run->getProcess());
                }
                $hit = true;
            }
        );

        $this->parentPool->allows()
                         ->add(Mockery::type(RunInterface::class))
                         ->once();

        $collection->add($this->process);

        $this->assertEquals(1, $collection->count());
        $runs = $collection->getAll();
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
        $run->allows()
            ->addListener(RunEvent::FAILED, Mockery::type('callable'));

        $collection = new Pool([$run]);

        $run->shouldReceive('start');
        $collection->run(0);

        $this->assertTrue($collection->hasStarted());
        $this->assertFalse($collection->isRunning());
        $this->assertTrue($collection->isSuccessful());
    }

    public function testSuccessfulRunWithEvents()
    {
        $run = new CallbackRun(function () {
            return true;
        });

        $collection = new RunCollection(new Pool(), [$run]);

        $startedHit = false;
        $completedHit = false;

        $collection->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) use ($collection, &$startedHit) {
                $this->assertSame($collection, $event->getRun());
                $startedHit = true;
            }
        );
        $collection->addListener(
            RunEvent::COMPLETED,
            function (RunEvent $event) use ($collection, &$completedHit) {
                $this->assertSame($collection, $event->getRun());
                $completedHit = true;
            }
        );

        $collection->run(0);

        $this->assertTrue($collection->hasStarted());
        $this->assertFalse($collection->isRunning());
        $this->assertTrue($collection->isSuccessful());

        $this->assertTrue($startedHit);
        $this->assertTrue($completedHit);
    }

    public function testFailedRunWithEvents()
    {
        $exception = new \RuntimeException('bwark');
        $run = new CallbackRun(function () use ($exception) {
            throw $exception;
        });

        $collection = new RunCollection(new Pool(), [$run]);

        $failedHit = false;

        $collection->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) use ($collection, &$failedHit) {
                $this->assertSame($collection, $event->getRun());
                $failedHit = true;
            }
        );

        $collection->run(0);

        $this->assertTrue($collection->hasStarted());
        $this->assertFalse($collection->isRunning());
        $this->assertFalse($collection->isSuccessful());

        $this->assertTrue($failedHit);

        $this->assertEquals([$exception], $collection->getExceptions());
    }

    public function testRunCollectionAbleToAddRunningProcessWhenPoolHasStarted()
    {
        $run = Mockery::mock(RunInterface::class)
                      ->allows(['isRunning' => false, 'hasStarted' => false, 'start' => null, 'addListener' => true, 'getPriority' => 1.0]);
        $collection = new Pool([$run]);
        $collection->start();

        $run2 = Mockery::mock(RunInterface::class)
                       ->allows(['isRunning' => true, 'start' => null, 'addListener' => true, 'getPriority' => 1.0]);
        $collection->add($run2);

        $this->assertEquals(2, $collection->count());
        $this->assertTrue($collection->isRunning());
    }

    public function testOnProgressIsCalledDuringProcessRun()
    {
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('stop');
        $process->shouldReceive('isStarted')->andReturn(false, false, true);
        $process->shouldReceive('isRunning')->andReturn(false, true, false);
        $process->shouldReceive('start')->atLeast()->once();
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);

        $hit = false;

        $collection = new RunCollection(new Pool());
        $collection->addListener(
            RunEvent::UPDATED,
            function (RunEvent $event) use ($collection, &$hit) {
                $run = $event->getRun();
                $this->assertEquals($collection, $run);
                $hit = true;
            }
        );

        $collection->add($process);
        $collection->run(0);
        $this->assertTrue($hit);
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

        $collection = new Pool([$run]);

        $run->shouldReceive('start');
        $collection->run(0);

        $this->assertEquals([$run], $collection->getFinished());
    }

    public function testTags()
    {
        $collection = new Pool([], Pool::NO_MAX, false, ['tag1', 'key' => 'value']);

        $this->assertSame(['tag1', 'key' => 'value'], $collection->getTags());
    }

    public function testProgress()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')->andReturn(false, true, false);
        $run->shouldReceive('poll')->andReturn(true, false);
        $run->shouldReceive('hasStarted')->andReturn(false, false, true);
        $run->shouldReceive('isSuccessful')->andReturn(true);
        $run->shouldReceive('getPriority')->andReturn(0.1);
        $run->allows()->addListener(RunEvent::FAILED, Mockery::type('callable'));

        $collection = new Pool([$run]);

        $this->assertEquals([0, 1, 0], $collection->getProgress());

        $run->shouldReceive('start');
        $collection->run(0);

        $this->assertEquals([1, 1, 1], $collection->getProgress());
    }

    public function testDuration()
    {
        $run = Mockery::mock(RunInterface::class);
        $run->shouldReceive('isRunning')->andReturn(false, true, false);
        $run->shouldReceive('poll')->andReturn(true, false);
        $run->shouldReceive('hasStarted')->andReturn(false, false, true);
        $run->shouldReceive('isSuccessful')->andReturn(true);
        $run->shouldReceive('getPriority')->andReturn(0.1);
        $run->allows()->addListener(RunEvent::FAILED, Mockery::type('callable'));

        $collection = new Pool([$run]);

        $this->assertEquals(0, $collection->getDuration());

        $run->shouldReceive('start');

        $collection->start();

        $this->assertGreaterThan(0, $collection->getDuration());

        $collection->run(0);

        $duration = $collection->getDuration();

        $this->assertEquals($duration, $collection->getDuration());
    }
}
