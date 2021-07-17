<?php

namespace Silverstripe\GarbageCollection\Tests\Processors;

use SilverStripe\Dev\SapphireTest;
use Silverstripe\GarbageCollection\Tests\Ship;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\DB;
use Silverstripe\GarbageCollection\Processors\SQLExpressionProcessor;

class SQLExpressionProcessorTest extends SapphireTest
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

    public function testGetName()
    {
        $class = Ship::class;

        // Create versioned records for testing deletion
        $model = $this->objFromFixture($class, 'ship1');

        $expression = SQLDelete::create(
            [
                $model->baseTable(),
            ]
        );

        $processor = new SQLExpressionProcessor($expression);
        $name = $processor->getName();
        $this->assertEquals($name, 'GarbageCollection_Ship');

        $processor = new SQLExpressionProcessor($expression, 'TestName');
        $name = $processor->getName();
        $this->assertEquals($name, 'TestName');
    }

    public function testProcess()
    {
        $class = Ship::class;

        // Create versioned records for testing deletion
        $model = $this->objFromFixture($class, 'ship1');
        $baseTable = sprintf('"%s"', $model->baseTable());
        $values = [
            'TestShip2',
            'TestShip3'
        ];

        // SQLSelect should be converted to SQLDelete
        $expression = SQLSelect::create(
            [
                "Title"
            ],
            [
                $baseTable,
            ],
            [
                sprintf($baseTable . '."Title" IN (%s)', DB::placeholders($values)) => $values
            ]
        );

        $processor = new SQLExpressionProcessor($expression);
        $count = $processor->process();
        // SQLSelect should be converted to SQLDelete
        // 2 records should have been removed 
        $this->assertEquals($count, 2);

        // 1 record should remain
        $this->assertEquals(Ship::get()->count(), 1);
    }
}
