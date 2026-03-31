<?php

namespace SilverStripe\GarbageCollector\Tests\Collectors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\GarbageCollector\Tests\MockProcessor;

class AbstractCollectorTest extends SapphireTest
{
    public function testProcessors()
    {
        $collector = new MockCollector();
        $this->assertEquals([MockProcessor::class], $collector->getProcessors());
    }
}
