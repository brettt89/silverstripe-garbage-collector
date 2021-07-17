<?php

namespace Silverstripe\GarbageCollection\Tests\Jobs;

use SilverStripe\Dev\SapphireTest;
use Silverstripe\GarbageCollection\ProcessorInterface;
use Silverstripe\GarbageCollection\CollectorInterface;
use Silverstripe\GarbageCollection\Jobs\GarbageCollectionJob;
use Silverstripe\GarbageCollection\Tests\Ship;
use Silverstripe\GarbageCollection\Tests\MockProcessor;

class GarbageCollectionJobTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'tests/php/SQLTest.yml';

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        Ship::class,
    ];

    public function testSetup()
    {
        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->expects($this->once())
                      ->method('getCollections')
                      ->will($this->returnValue([]));

        $mockCollector->expects($this->once())
                      ->method('getProcessors')
                      ->will($this->returnValue([MockProcessor::class]));
        
        $job = new GarbageCollectionJob($mockCollector);

        $job->setup();
        $data = $job->getJobData();

        $this->assertEquals($mockCollector, $data->jobData->collector);
        $this->assertEquals([Ship::class => MockProcessor::class], $data->jobData->processors);
        $this->assertEquals(0, $data->totalSteps);
        $this->assertEquals([], $data->jobData->remaining);
    }

    /**
     * 
     */
    public function testEmptyCollection()
    {
        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->expects($this->once())
                      ->method('getCollections')
                      ->will($this->returnValue([]));

        $mockCollector->expects($this->once())
                      ->method('getProcessors')
                      ->will($this->returnValue([]));
        
        $job = new GarbageCollectionJob($mockCollector);
        $job->setup();
        $job->process();

        $this->assertTrue($job->jobFinished());
    }

    /**
     * @expectedException           Exception
     * @expectedExceptionMessage    No Processors found for collector
     */
    public function testEmptyProcesses()
    {
        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->expects($this->once())
                      ->method('getCollections')
                      ->will($this->returnValue([['foo' => 'bar'], ['bar' => 'foo']]));

        $mockCollector->expects($this->once())
                      ->method('getProcessors')
                      ->will($this->returnValue([]));
        
        $job = new GarbageCollectionJob($mockCollector);
        $job->setup();
        $job->process();
    }

    /**
     * Test both array of items and array of arrays can be processed
     */
    public function testProcess()
    {
        $ship1 = $this->objFromFixture(Ship::class, 'ship1');
        $ship2 = $this->objFromFixture(Ship::class, 'ship2');
        $ship3 = $this->objFromFixture(Ship::class, 'ship3');

        
        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->expects($this->once())
                      ->method('getCollections')
                      ->will($this->returnValue([
                          [
                              [$ship2, $ship3]
                          ],
                          [$ship1],
                      ]));

        $mockCollector->method('getProcessors')
                      ->will($this->returnValue([MockProcessor::class]));
        
        $job = new GarbageCollectionJob($mockCollector);
        $job->setup();
        $job->process();

        $data = $job->getJobData();
        # Expected 3 times, once for each ship
        $this->assertContains('[INFO] Processed 4 records for ' . Ship::class . ' using MockProcessor', array_shift($data->messages));
        $this->assertContains('[INFO] Processed 4 records for ' . Ship::class . ' using MockProcessor', array_shift($data->messages));
        $this->assertContains('[INFO] Processed 4 records for ' . Ship::class . ' using MockProcessor', array_shift($data->messages));

        # No further messages should exist and job should be completed.
        $this->assertEmpty($data->messages);
        $this->assertTrue($job->jobFinished());
    }
}