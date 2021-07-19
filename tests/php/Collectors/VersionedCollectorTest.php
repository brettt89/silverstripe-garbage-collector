<?php

namespace SilverStripe\GarbageCollector\Tests\Collectors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\GarbageCollector\Collectors\VersionedCollector;
use SilverStripe\GarbageCollector\GarbageCollectorService;
use SilverStripe\Versioned\Versioned;
use SilverStripe\GarbageCollector\Tests\Ship;
use SilverStripe\Core\Config\Config;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;

class VersionedCollectorTest extends SapphireTest
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

        $records = Config::withConfig(function(MutableConfigCollectionInterface $config) use ($deletion_limit) {
            // Add Ship to base_classes for VersionedCollector
            $config->set(VersionedCollector::class, 'base_classes', [ Ship::class ]);

            // If we are using a custom deletion limit for test, apply it
            if (isset($deletion_limit)) {
                $config->set(VersionedCollector::class, 'deletion_version_limit', $deletion_limit);
            }

            $collector = new VersionedCollector();
            return $collector->getCollections();
        });

        $this->assertCount(count($expected), $records);
        if (count($expected) === 0) {
            return;
        }

        // Loop over expected results and check
        foreach ($expected as $key => $exptectedData) {
            $where = $records[$key]->getWhere();
            $recordWhere = array_shift($where);
            $versionWhere = array_shift($where);

            $this->assertSame($exptectedData['recordId'], array_shift($recordWhere)[0]);
            $this->assertSame($exptectedData['versionIds'], array_shift($versionWhere));
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
                '+ 184 days',
                [
                    [
                        'recordId' => 2,
                        'versionIds' => [ 1, 2, 3 ]
                    ]
                ]
            ],
            'Versions passed lifetime, Multi Query' => [
                'ship3',
                '+ 185 days',
                [
                    [
                        'recordId' => 3,
                        'versionIds' => [ 1, 2 ]
                    ],
                    [
                        'recordId' => 3,
                        'versionIds' => [ 3, 4 ]
                    ]
                ],
                2
            ]
        ];
    }

    /**
     * @param DataObject|Versioned $model
     * @throws ValidationException
     * @throws Exception
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
