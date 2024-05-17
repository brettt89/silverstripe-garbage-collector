<?php

namespace SilverStripe\GarbageCollector\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GarbageCollector\CollectorInterface;
use SilverStripe\GarbageCollector\GarbageCollectorService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;

class GarbageCollectorServiceTest extends SapphireTest
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

    private $service;
    private $logger;
    private $mockCollector1;
    private $mockCollector2;

    public function setUp(): void
    {
        $this->logger = new \Monolog\Handler\TestHandler();

        $this->service = GarbageCollectorService::inst();
        $this->service->setLogger(new \Monolog\Logger('TestLogger', [
            $this->logger
        ]));

        // Setup default mock collector services
        $this->mockCollector1 = $this->createMock(CollectorInterface::class);
        $this->mockCollector1->method('getName')
                             ->will($this->returnValue('MyTestCollector'));

        $this->mockCollector2 = $this->createMock(CollectorInterface::class);
        $this->mockCollector2->method('getName')
                             ->will($this->returnValue('MyOtherTestCollector'));

        // Register mocks
        Injector::inst()->registerService($this->mockCollector1, 'MyTestCollector');
        Injector::inst()->registerService($this->mockCollector2, 'MyOtherTestCollector');

        parent::setUp();
    }

    /**
     * Test collectors via Config yml
     */
    public function testGetCollectors()
    {
        $result = Config::withConfig(function (MutableConfigCollectionInterface $config) {
            // update Service to use mock collector
            $config->set(GarbageCollectorService::class, 'collectors', [
                'MyTestCollector',
                'MyOtherTestCollector'
            ]);

            // get Collectors for testing
            return GarbageCollectorService::inst()->getCollectors();
        });

        // Ensure 2 collectors are returned
        $this->assertCount(2, $result);

        // Ensure both collectors implement CollectorInteface
        $this->assertInstanceOf(CollectorInterface::class, $result[0]);
        $this->assertInstanceOf(CollectorInterface::class, $result[1]);

        // Ensure both collectors are unique as expected
        $this->assertEquals('MyTestCollector', $result[0]->getName());
        $this->assertEquals('MyOtherTestCollector', $result[1]->getName());
    }

    public function testNoProcessorsRegistered()
    {
        $this->mockCollector1->expects($this->once())
                             ->method('getProcessors')
                             ->will($this->returnValue([]));

        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            // update Service to use mock collector
            $config->set(GarbageCollectorService::class, 'collectors', [
                'MyTestCollector'
            ]);

            // get Collectors for testing
            return $this->service->process();
        });

        $this->assertTrue($this->logger->hasNoticeThatContains('No processors registered with Collector'));
    }

    public function testNoCollectionsProcessors()
    {

        $this->service->processCollection([], []);
        $this->assertTrue($this->logger->hasNoticeThatContains('No Processors provided for Collection'));
    }

    public function testNoCollectionsProcessorMatch()
    {
        $mockProcessor = new MockProcessor(2);
        Injector::inst()->registerService($mockProcessor, 'MyProcessor');

        $this->mockCollector1->expects($this->once())
                             ->method('getProcessors')
                             ->will($this->returnValue(['MyProcessor']));

        $this->mockCollector1->expects($this->once())
                             ->method('getCollections')
                             ->will($this->returnValue([
                                 [ new \stdClass() ]
                             ]));

        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            // update Service to use mock collector
            $config->set(GarbageCollectorService::class, 'collectors', [
                'MyTestCollector'
            ]);

            // get Collectors for testing
            return $this->service->processCollector($this->mockCollector1);
        });

        $this->assertTrue($this->logger->hasNoticeThatContains('Unable to find processor for stdClass'));
    }

    public function testProcessSuccess()
    {
        $collection = [
            $this->objFromFixture(Ship::class, 'ship1'),
            $this->objFromFixture(Ship::class, 'ship2')
        ];

        $mockProcessor = new MockProcessor($collection);
        Injector::inst()->registerService($mockProcessor, 'MyProcessor');

        $this->mockCollector1->expects($this->once())
                             ->method('getProcessors')
                             ->will($this->returnValue(['MyProcessor']));

        $this->mockCollector1->expects($this->once())
                             ->method('getCollections')
                             ->will($this->returnValue([
                                 [ $collection ]
                             ]));

        $result = Config::withConfig(function (MutableConfigCollectionInterface $config) use ($collection) {
            // update Service to use mock collector
            $config->set(GarbageCollectorService::class, 'collectors', [
                'MyTestCollector'
            ]);

            // get Collectors for testing
            return $this->service->process();
        });

        $logs = $this->logger->getRecords();
        // We expect 2 Succesful Processed log entries to exist
        $this->assertCount(2, $logs);
        $this->assertEquals($logs[0]['message'], sprintf('Processed 1 records for %s using MockProcessor', Ship::class));
        $this->assertEquals($logs[1]['message'], sprintf('Processed 1 records for %s using MockProcessor', Ship::class));
    }
}
