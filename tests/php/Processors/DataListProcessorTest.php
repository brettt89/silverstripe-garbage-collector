<?php

namespace SilverStripe\GarbageCollector\Tests\Processors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataList;
use SilverStripe\GarbageCollector\Tests\Ship;
use SilverStripe\GarbageCollector\Processors\DataListProcessor;

class DataListProcessorTest extends SapphireTest
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
    ];

    public function testProcessor()
    {
        // Pass 2 records to be removed.
        $list = Ship::get()->limit(2);

        $processor = new DataListProcessor($list);
        $count = $processor->process();

        // 2 records should have been removed
        $this->assertEquals($count, 2);
        // 1 record should remain
        $this->assertEquals(Ship::get()->count(), 1);
        $this->assertEquals(Ship::class, $processor->getName());

        $processor = new DataListProcessor($list, 'TestName');
        $this->assertEquals('TestName', $processor->getName());
        $this->assertEquals(DataList::class, $processor->getImplementorClass());
    }
}
