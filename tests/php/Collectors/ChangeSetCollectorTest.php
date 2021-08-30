<?php

namespace SilverStripe\GarbageCollector\Tests\Collectors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\GarbageCollector\Tests\CargoShip;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\GarbageCollector\Collectors\ChangeSetCollector;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use SilverStripe\GarbageCollector\Tests\Ship;
use SilverStripe\Core\Config\Config;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;

class ChangeSetCollectorTest extends SapphireTest
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

    /**
     * @var string[][]
     */
    protected static $required_extensions = [
        Ship::class => [
            Versioned::class,
        ],
    ];

    /**
     * @param string $class
     * @param string $now
     * @param array $expected
     * @throws ValidationException
     * @dataProvider collectionsProvider
     */
    public function testGetCollections(string $id, string $modifyDate = null, array $expected = [], int $deletion_limit = null): void
    {
        $model = $this->objFromFixture(Ship::class, $id);
        $this->createTestVersions($model);
        $baseClass = $model->baseClass();

        // Modify date for expiration
        $mockDate = DBDatetime::now();
        if ($modifyDate) {
            $mockDate = $mockDate->modify($modifyDate);
        }
        DBDatetime::set_mock_now($mockDate);

        $records = Config::withConfig(function (MutableConfigCollectionInterface $config) use ($deletion_limit) {
            if (isset($deletion_limit)) {
                $config->set(ChangeSetCollector::class, 'deletion_limit', $deletion_limit);
            }
            $collector = new ChangeSetCollector();
            return $collector->getCollections();
        });

        $this->assertCount(count($expected), $records);
        if (count($expected) === 0) {
            return;
        }

        foreach ($expected as $key => $ids) {
            $where = $records[$key]->getWhere();
            $this->assertSame($ids, array_shift($where[0]));
        }
    }

    public function collectionsProvider(): array
    {
        return [
            'No versions passed lifetime' => [
                'ship1'
            ],
            'Versions passed lifetime' => [
                'ship2',
                '+ 110 days',
                [
                    [ 4, 5, 6 ]
                ]
            ],
            'Versions passed lifetime, Multi Query' => [
                'ship3',
                '+ 110 days',
                [
                    [ 7, 8 ],
                    [ 9 ]
                ],
                2
            ]
        ];
    }

    /**
     * @param DataObject|Versioned $model
     * @throws ValidationException
     * @throws \Exception
     */
    private function createTestVersions(DataObject $model): void
    {
        $mockRange = range(1, 10);

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
}
