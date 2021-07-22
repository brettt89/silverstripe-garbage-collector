<?php

namespace SilverStripe\GarbageCollector\Tests\Collectors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\GarbageCollector\Collectors\ObsoleteTableCollector;
use SilverStripe\GarbageCollector\Tests\Ship;

class ObsoleteTableCollectorTest extends SapphireTest
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

    public function testGetCollections(): void
    {
        // Create test Tables
        
        DB::query('CREATE TABLE "Test_Table1" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "Test_Table2" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "Test_Table3" (id INTEGER PRIMARY KEY)');

        // Create obsolete tables

        DB::query('CREATE TABLE "_obsolete_Test_Table1" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "_obsolete_Test_Table2" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "_obsolete_Test_Table3" (id INTEGER PRIMARY KEY)');

        $collector = new ObsoleteTableCollector();
        $result = $collector->getCollections();

        // We expect 3 drop statements to exist
        $this->assertCount(3, $result);

        $this->assertEquals('DROP TABLE \'_obsolete_Test_Table1\'', $result[0]->sql());
        $this->assertEquals('DROP TABLE \'_obsolete_Test_Table2\'', $result[1]->sql());
        $this->assertEquals('DROP TABLE \'_obsolete_Test_Table3\'', $result[2]->sql());
    }
}
