<?php

namespace SilverStripe\GarbageCollector\Tests\Processors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\GarbageCollector\Tests\Ship;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLExpression;
use SilverStripe\ORM\DB;
use SilverStripe\GarbageCollector\Processors\SQLExpressionProcessor;

class SQLExpressionProcessorTest extends SapphireTest
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

        // Ensure base table is used for name
        $name = $processor->getName();
        $this->assertEquals('GarbageCollector_Ship', $name);
        $this->assertEquals(SQLExpression::class, $processor->getImplementorClass());

        // Test overloading naming through constructor
        $processor = new SQLExpressionProcessor($expression, 'TestName');
        $name = $processor->getName();
        $this->assertEquals('TestName', $name);
    }
}
