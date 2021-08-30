<?php

namespace SilverStripe\GarbageCollector\Tests\Collectors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\GarbageCollector\Tests\CargoShip;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Config\Config;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
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
        CargoShip::class,
    ];

    public function testGetName(): void
    {
        $collector = new ObsoleteTableCollector();
        $this->assertEquals('ObsoleteTableCollector', $collector->getName());
    }

    public function testGetCollections(): void
    {
        $collector = new ObsoleteTableCollector();

        // Create test Tables
        DB::query('CREATE TABLE "Test_Table1" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "Test_Table2" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "Test_Table3" (id INTEGER PRIMARY KEY)');

        $records = $collector->getCollections();
        // There shouldn't be any collections at this point.
        $this->assertCount(0, $records);

        // Create obsolete tables
        DB::query('CREATE TABLE "_obsolete_Test_Table1" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "_obsolete_Test_Table2" (id INTEGER PRIMARY KEY)');
        DB::query('CREATE TABLE "_obsolete_Test_Table3" (id INTEGER PRIMARY KEY)');

        // Test with skip_tables
        $records = Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ObsoleteTableCollector::class, 'skip_tables', ['Test_Table1']);

            $collector = new ObsoleteTableCollector();
            return $collector->getCollections();
        });

        // We expect 2 drop statements to exist
        $this->assertCount(2, $records);

        $this->assertEquals('DROP TABLE \'_obsolete_Test_Table2\'', $records[0]->sql());
        $this->assertEquals('DROP TABLE \'_obsolete_Test_Table3\'', $records[1]->sql());
    }
}
