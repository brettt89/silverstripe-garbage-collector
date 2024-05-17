<?php

namespace SilverStripe\GarbageCollector\Tests\Jobs;

use Exception;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GarbageCollector\CollectorInterface;
use SilverStripe\GarbageCollector\Jobs\GarbageCollectorJob;
use SilverStripe\GarbageCollector\Tests\CargoShip;
use SilverStripe\GarbageCollector\Tests\MockProcessor;
use SilverStripe\GarbageCollector\Tests\Ship;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

class GarbageCollectorJobTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'tests/php/Models.yml';

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        Ship::class,
        CargoShip::class,
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!class_exists(AbstractQueuedJob::class)) {
            self::markTestSkipped('This test requires the queuedjobs module to be installed');
        }
    }


    public function testSetup()
    {
        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->expects($this->once())
                      ->method('getName')
                      ->will($this->returnValue('MockCollector'));

        $mockCollector->expects($this->once())
                      ->method('getCollections')
                      ->will($this->returnValue([]));

        $mockCollector->expects($this->once())
                      ->method('getProcessors')
                      ->will($this->returnValue([MockProcessor::class]));

        $job = new GarbageCollectorJob($mockCollector);

        $job->setup();
        $data = $job->getJobData();

        $this->assertEquals($mockCollector, $data->jobData->collector);
        $this->assertEquals([Ship::class => MockProcessor::class], $data->jobData->processors);
        $this->assertEquals(0, $data->totalSteps);
        $this->assertEquals([], $data->jobData->remaining);

        // Test JobType and Title
        $this->assertEquals(QueuedJob::QUEUED, $job->getJobType());
        $this->assertEquals('Garbage Collection processing for MockCollector collector', $job->getTitle());
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

        $job = new GarbageCollectorJob($mockCollector);
        $job->setup();
        $job->process();

        $this->assertTrue($job->jobFinished());
    }

    /**
     *
     */
    public function testUnmatchedCollection()
    {
        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->expects($this->once())
                      ->method('getCollections')
                      ->will($this->returnValue([[new \stdClass()]]));

        $mockCollector->expects($this->once())
                      ->method('getProcessors')
                      ->will($this->returnValue([MockProcessor::class]));

        $job = new GarbageCollectorJob($mockCollector);
        $job->setup();
        $job->process();

        $data = $job->getJobData();
        # Expected 3 times, once for each ship
        $this->assertStringContainsString('[NOTICE] Unable to find processor for stdClass', array_shift($data->messages));
        $this->assertEmpty($data->messages);

        $this->assertTrue($job->jobFinished());
        $this->assertEquals($data->totalSteps, $data->currentStep);
    }

    /**
     * @expectedException           Exception
     * @expectedExceptionMessage    No Processors found for collector
     */
    public function testEmptyProcesses()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No Processors found for collector');

        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->expects($this->once())
                      ->method('getCollections')
                      ->will($this->returnValue([['foo' => 'bar'], ['bar' => 'foo']]));

        $mockCollector->expects($this->once())
                      ->method('getProcessors')
                      ->will($this->returnValue([]));

        $job = new GarbageCollectorJob($mockCollector);
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

        $job = new GarbageCollectorJob($mockCollector);
        $job->setup();
        $job->process();

        $data = $job->getJobData();
        # Expected 3 times, once for each ship
        $this->assertStringContainsString('[INFO] Processed 1 records for ' . Ship::class . ' using MockProcessor', array_shift($data->messages));
        $this->assertStringContainsString('[INFO] Processed 1 records for ' . Ship::class . ' using MockProcessor', array_shift($data->messages));
        $this->assertStringContainsString('[INFO] Processed 1 records for ' . Ship::class . ' using MockProcessor', array_shift($data->messages));

        # No further messages should exist and job should be completed.
        $this->assertEmpty($data->messages);
        $this->assertEquals($data->totalSteps, $data->currentStep);
        $this->assertTrue($job->jobFinished());
    }
}
