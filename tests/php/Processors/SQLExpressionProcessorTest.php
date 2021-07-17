<?php

namespace Silverstripe\GarbageCollection\Tests\Processors;

use SilverStripe\Dev\SapphireTest;
use Silverstripe\GarbageCollection\Tests\Ship;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\DB;
use Silverstripe\GarbageCollection\Processors\SQLExpressionProcessor;

class SQLExpressionProcessorTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'SQLTest.yml';

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        Ship::class,
    ];

    protected function setUp(): void
    {
        DBDatetime::set_mock_now('2020-01-01 00:00:00');
        parent::setUp();
    }

    /**
     * @param DataObject|Versioned $model
     * @throws ValidationException
     * @throws Exception
     */
    private function createTestVersions(DataObject $model, int $mockRange = 10): void
    {
        foreach ($mockRange as $i) {
            $mockDate = DBDatetime::create_field('Datetime', DBDatetime::now()->Rfc2822())
                ->modify(sprintf('+ %d days', $i))
                ->Rfc2822();

            DBDatetime::withFixedNow($mockDate, static function () use ($model, $i): void {
                $model->Title = 'Iteration ' . $i;
                $model->write();

                if (($i % 3) !== 0) {
                    return;
                }

                $model->publishRecursive();
            });
        }
    }

    public function testGetName()
    {
        $class = Ship::class;

        // Create versioned records for testing deletion
        $model = $this->objFromFixture($class, 'ship1');
        $this->createTestVersions($model, 4);

        $baseTable = $model->baseTable();
        $versions = [1, 2, 3];

        $expression = SQLDelete::create(
            [
                $baseTable,
            ],
            [
                // We are deleting specific versions for specific record
                $baseTable . '."RecordID"' => $recordId,
                sprintf($baseTable . '."Version" IN (%s)', DB::placeholders($versions)) => $versions,
            ],
            $baseTables,
        );

        $processor = new SQLExpressionProcessor($expression);
        $name = $processor->getName();
        $this->assertEquals($name, 'GarbageCollection_Ship');
    }
}
