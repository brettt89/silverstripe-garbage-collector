<?php

namespace Silverstripe\GarbageCollector\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use Silverstripe\GarbageCollector\CollectorInterface;
use Silverstripe\GarbageCollector\GarbageCollectorService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;

class GarbageCollectorServiceTest extends SapphireTest
{   
    /**
     * Test collectors via Config yml
     */
    public function testCollectors()
    {
        // Setup default mock collector services
        $mockCollector1 = $this->createMock(CollectorInterface::class);
        $mockCollector1->method('getName')
                       ->will($this->returnValue('MyTestCollector'));
        $mockCollector2 = $this->createMock(CollectorInterface::class);
        $mockCollector2->method('getName')
                       ->will($this->returnValue('MyOtherTestCollector'));

        Injector::inst()->registerService($mockCollector1, 'MyTestCollector');
        Injector::inst()->registerService($mockCollector2, 'MyOtherTestCollector');
        
        $result = Config::withConfig(function(MutableConfigCollectionInterface $config) {
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
}