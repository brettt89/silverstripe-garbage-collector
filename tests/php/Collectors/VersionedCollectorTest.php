<?php

namespace SilverStripe\GarbageCollector\Tests\Collectors;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\GarbageCollector\Tests\CargoShip;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\GarbageCollector\Collectors\VersionedCollector;
use SilverStripe\ORM\ValidationException;
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
        CargoShip::class,
    ];

    /**
     * @var string[][]
     */
    protected static $required_extensions = [
        Ship::class => [
            Versioned::class,
        ],
        CargoShip::class => [
            Versioned::class,
        ],
    ];

    /**
     * @param string $id
     * @param ?string $modifyDate
     * @param array $expected
     * @param ?int $deletion_limit
     * @param ?int $keep_limit
     * @param bool $keep_unpublished_drafts
     * @param string $model_class
     * @throws ValidationException
     * @dataProvider collectionsProvider
     */
    public function testGetCollections(
        string $id,
        string $modifyDate = null,
        array $expected = [],
        int $deletion_limit = null,
        int $keep_limit = null,
        bool $keep_unpublished_drafts = false,
        string $model_class = Ship::class
    ): void
    {
        $model = $this->objFromFixture($model_class, $id);
        $this->createTestVersions($model);

        // Modify date for expiration
        $mockDate = DBDatetime::now();
        if ($modifyDate) {
            $mockDate = $mockDate->modify($modifyDate);
        }
        DBDatetime::set_mock_now($mockDate);

        $records = Config::withConfig(function (MutableConfigCollectionInterface $config) use ($deletion_limit, $keep_limit, $keep_unpublished_drafts) {
            // Add Ship to base_classes for VersionedCollector
            $config->set(VersionedCollector::class, 'base_classes', [ Ship::class ]);

            // If we are using a custom deletion limit for test, apply it
            if (isset($deletion_limit)) {
                $config->set(VersionedCollector::class, 'deletion_version_limit', $deletion_limit);
            }

            // If we are using a custom keep limit for test, apply it
            if (isset($keep_limit)) {
                $config->set(VersionedCollector::class, 'keep_limit', $keep_limit);
            }

            // If we keep unpublished flags, set the config
            if ($keep_unpublished_drafts) {
                $config->set(VersionedCollector::class, 'keep_unpublished_drafts', $keep_unpublished_drafts);
            }

            $collector = new VersionedCollector();
            return $collector->getCollections();
        });

        $this->assertCount(count($expected), $records);
        if (count($expected) === 0) {
            return;
        }

        // Loop over expected results and check
        foreach ($expected as $key => $expectedData) {
            $where = $records[$key]->getWhere();
            $delete = $records[$key]->getDelete();

            $this->assertSame($expectedData['tables'], $delete);

            $recordWhere = array_shift($where);
            $versionWhere = array_shift($where);

            $this->assertSame($expectedData['recordId'], array_shift($recordWhere)[0]);
            $this->assertSame($expectedData['versionIds'], array_shift($versionWhere));
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
                        'versionIds' => [ 1, 2, 3 ],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"'
                        ]
                    ]
                ]
            ],
            'Versions passed lifetime, Multi Query, Keep one version ' => [
                'ship3',
                '+ 185 days',
                [
                    [
                        'recordId' => 3,
                        'versionIds' => [ 1, 2 ],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"'
                        ]
                    ],
                    [
                        'recordId' => 3,
                        'versionIds' => [ 3, 4 ],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"'
                        ]
                    ],
                    [
                        'recordId' => 3,
                        'versionIds' => [ 6 ],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"'
                        ]
                    ]
                ],
                2, // delete in batch of 2
                1, // only keep 1 draft version
            ],
            'Only versions before first published, keep only 1 draft' => [
                'ship4',
                '+ 1 year',
                [
                    [
                        'recordId' => 4,
                        // 5 is published as it's the 4th version after the initial object is created
                        // when creating the mock version data, every 3rd version is published
                        // (creating draft and published, so two versions, hence 4th in the row)
                        'versionIds' => [ 1, 2, 3, 4, 6],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"',
                        ],
                    ],
                    [
                        'recordId' => 4,
                        // 9 is published as it's 4th versions after 5 etc., see the longer explanation above
                        // 12 is not present as it's within the keep limit
                        // 13 is the latest published version (4th version after 9)
                        // 14 is the version newer than latest published version and we keep unpublished drafts
                        'versionIds' => [ 7, 8, 10, 11],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"',
                        ],
                    ],
                ],
                5, // delete in batch of 5
                1, // only keep one draft version
                true, // keep unpublished drafts
            ],
            'MTI model' => [
                'cargoship1',
                '+ 1 year',
                [
                    [
                        'recordId' => 6,
                        // 5 is published as it's the 4th version after the initial object is created
                        // when creating the mock version data, every 3rd version is published
                        // (creating draft and published, so two versions, hence 4th in the row)
                        'versionIds' => [ 1, 2, 3, 4, 6, 7, 8, 10, 11],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"',
                            '"GarbageCollector_CargoShip_Versions"',
                        ],
                    ],
                ],
                10, // delete in batch of 5
                null, // only keep one draft version
                false,
                CargoShip::class,
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
