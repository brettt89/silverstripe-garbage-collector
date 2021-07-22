<?php

namespace SilverStripe\GarbageCollector\Tests\Processors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\GarbageCollector\Tests\Ship;
use SilverStripe\GarbageCollector\Models\RawSQL;
use SilverStripe\GarbageCollector\Processors\RawSQLProcessor;

class RawSQLProcessorTest extends SapphireTest
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
        // Create test table
        DB::query('CREATE TABLE "Test_Table" (id INTEGER PRIMARY KEY)');
        $query = new RawSQL('DROP TABLE "Test_Table"');

        // Ensure table exists
        $result = DB::query('SHOW TABLES LIKE \'Test_Table\'')->column();
        $this->assertCount(1, $result);

        // Use RawSQLProcessor to DROP the table
        $processor = new RawSQLProcessor($query);
        $count = $processor->process();

        // Confirm table has actually been dropped.
        $result = DB::query('SHOW TABLES LIKE \'Test_Table\'')->column();

        // 1 table should have been removed
        $this->assertEquals(1, $count);
        // 0 tables should remain
        $this->assertCount(0, $result);

        // Ensure basetable is used for name
        $name = $processor->getName();
        $this->assertEquals('RawSQLProcessor', $name);
        $this->assertEquals(RawSQL::class, $processor->getImplementorClass());
    }
}
