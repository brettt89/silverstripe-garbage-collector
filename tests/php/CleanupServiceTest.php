<?php

namespace App\Tests\VersionsCleanup;

use App\Extensions\Locale\LocaleDefaultRecordsExtension;
use App\VersionsCleanup\CleanupJob;
use App\VersionsCleanup\CleanupService;
use Exception;
use ReflectionMethod;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\State\FluentState;

class CleanupServiceTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'CleanupServiceTest.yml';

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        House::class,
        Ship::class,
    ];

    /**
     * @var string[][]
     */
    protected static $required_extensions = [
        House::class => [
            FluentSiteTreeExtension::class,
        ],
        Ship::class => [
            Versioned::class,
        ],
    ];

    protected function setUp(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale(LocaleDefaultRecordsExtension::LOCALE_INTERNATIONAL_ENGLISH);

            DBDatetime::set_mock_now('2020-01-01 00:00:00');
            parent::setUp();
        });
    }

    /**
     * @param array $classes
     * @param array $expected
     * @dataProvider baseClassesProvider
     */
    public function testGetBaseClasses(array $classes, array $expected): void
    {
        CleanupService::config()->set('base_classes', $classes);
        $this->assertSame($expected, CleanupService::singleton()->getBaseClasses());
    }

    public function baseClassesProvider(): array
    {
        return [
            'Localised / Valid' => [
                [SiteTree::class],
                [SiteTree::class],
            ],
            'Not Localised / Valid' => [
                [Ship::class],
                [Ship::class],
            ],
            'Invalid' => [
                [SiteConfig::class],
                [],
            ],
        ];
    }

    /**
     * @param string $class
     * @param string $now
     * @param array $expected
     * @throws ValidationException
     * @dataProvider deletionRecordsProvider
     */
    public function testGetRecordsForDeletion(string $class, string $id, string $now, array $expected): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($class, $id, $now, $expected): void {
            $state->setLocale(LocaleDefaultRecordsExtension::LOCALE_INTERNATIONAL_ENGLISH);

            $model = $this->objFromFixture($class, $id);
             $this->createTestVersions($model);
            $baseClass = $model->baseClass();
            DBDatetime::set_mock_now($now);
            $records = CleanupService::singleton()->getRecordsForDeletion([$baseClass]);

            if (count($expected) === 0) {
                $this->assertCount(0, $records);

                return;
            }

            $this->assertArrayHasKey($baseClass, $records);
            $this->assertSame($expected, $records[$baseClass]);
        });
    }

    public function deletionRecordsProvider(): array
    {
        return [
            'Not Localised / No versions passed lifetime' => [
                Ship::class,
                'ship1',
                '2020-06-30 00:00:00',
                [],
            ],
            'Not Localised / Versions passed lifetime' => [
                Ship::class,
                'ship1',
                '2020-07-01 00:00:00',
                [
                    [
                        'id' => 1,
                    ],
                ],
            ],
            'Localised / No versions passed lifetime' => [
                House::class,
                'house1',
                '2020-06-29 00:00:00',
                [],
            ],
            'Localised / Versions passed lifetime' => [
                House::class,
                'house1',
                '2020-06-30 00:00:00',
                [
                    [
                        'id' => 1,
                        'locale' => LocaleDefaultRecordsExtension::LOCALE_INTERNATIONAL_ENGLISH,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string $class
     * @param array $config
     * @param int $expected
     * @throws ValidationException
     * @throws Exception
     * @dataProvider deletionRecordLimitProvider
     */
    public function testDeletionRecordLimit(
        string $class,
        array $config,
        int $expected
    ): void {
        foreach ($config as $key => $value) {
            CleanupService::config()->set($key, $value);
        }

        FluentState::singleton()->withState(function (FluentState $state) use ($class, $expected): void {
            $state->setLocale(LocaleDefaultRecordsExtension::LOCALE_INTERNATIONAL_ENGLISH);

            $models = DataObject::get($class);

            foreach ($models as $model) {
                $this->createTestVersions($model);
            }

            $baseClass = DataObject::singleton($class)->baseClass();
            DBDatetime::set_mock_now('2022-01-01 00:00:00');
            $records = CleanupService::singleton()->getRecordsForDeletion([$baseClass]);

            if ($expected === 0) {
                $this->assertCount(0, $records);

                return;
            }

            $this->assertArrayHasKey($baseClass, $records);
            $this->assertCount($expected, $records[$baseClass]);
        });
    }

    public function deletionRecordLimitProvider(): array
    {
        return [
            'Not Localised / Limit 1' => [
                House::class,
                ['deletion_record_limit' => 1],
                1,
            ],
            'Not Localised / Limit 2' => [
                House::class,
                ['deletion_record_limit' => 2],
                2,
            ],
            'Localised / Limit 1' => [
                Ship::class,
                ['deletion_record_limit' => 1],
                1,
            ],
            'Localised / Limit 2' => [
                Ship::class,
                ['deletion_record_limit' => 2],
                2,
            ],
        ];
    }

    /**
     * @param string $class
     * @param string $id
     * @param string $now
     * @param array $expected
     * @param array $config
     * @throws ValidationException
     * @throws Exception
     * @dataProvider deletionVersionsProvider
     */
    public function testGetVersionsForDeletion(
        string $class,
        string $id,
        string $now,
        array $expected,
        array $config = []
    ): void {
        foreach ($config as $key => $value) {
            CleanupService::config()->set($key, $value);
        }

        FluentState::singleton()->withState(function (FluentState $state) use ($class, $id, $now, $expected): void {
            $state->setLocale(LocaleDefaultRecordsExtension::LOCALE_INTERNATIONAL_ENGLISH);

            $model = $this->objFromFixture($class, $id);
            $this->createTestVersions($model);
            $baseClass = $model->baseClass();
            DBDatetime::set_mock_now($now);
            $mockData = [
                'id' => $model->ID,
            ];

            if ($model->hasExtension(FluentExtension::class)) {
                $mockData['locale'] = LocaleDefaultRecordsExtension::LOCALE_INTERNATIONAL_ENGLISH;
            }

            $versions = CleanupService::singleton()->getVersionsForDeletion([
                $baseClass => [
                    $mockData,
                ],
            ]);

            if (count($expected) === 0) {
                $this->assertCount(0, $versions);

                return;
            }

            $this->assertArrayHasKey($baseClass, $versions);
            $this->assertArrayHasKey($model->ID, $versions[$baseClass]);
            $this->assertArrayHasKey($model->ClassName, $versions[$baseClass][$model->ID]);
            $this->assertSame($expected, $versions[$baseClass][$model->ID][$model->ClassName]);
        });
    }

    public function deletionVersionsProvider(): array
    {
        return [
            'Not Localised / No versions passed lifetime' => [
                Ship::class,
                'ship1',
                '2020-06-30 00:00:00',
                [],
            ],
            'Not Localised / Versions passed lifetime' => [
                Ship::class,
                'ship1',
                '2020-07-01 00:00:00',
                [
                    1,
                ],
            ],
            'Not Localised / Versions skips published' => [
                Ship::class,
                'ship1',
                '2022-01-01 00:00:00',
                [
                    11,
                    10,
                    8,
                    7,
                    6,
                    4,
                    3,
                    2,
                    1,
                ],
            ],
            'Not Localised / Versions deletion limit' => [
                Ship::class,
                'ship1',
                '2022-01-01 00:00:00',
                [
                    11,
                ],
                [
                    'deletion_version_limit' => 1,
                ],
            ],
            'Not Localised / Versions keep more versions' => [
                Ship::class,
                'ship1',
                '2022-01-01 00:00:00',
                [
                    7,
                    6,
                    4,
                    3,
                    2,
                    1,
                ],
                [
                    'keep_limit' => 5,
                ],
            ],
            'Not Localised / Versions shorter lifetime' => [
                Ship::class,
                'ship1',
                '2020-06-30 00:00:00',
                [
                    1,
                ],
                [
                    'keep_lifetime' => 179,
                ],
            ],
            'Localised / No versions passed lifetime' => [
                House::class,
                'house1',
                '2020-06-29 00:00:00',
                [],
            ],
            'Localised / Versions passed lifetime' => [
                House::class,
                'house1',
                '2020-06-30 00:00:00',
                [
                    1,
                ],
            ],
            'Localised / Versions deletion limit' => [
                House::class,
                'house1',
                '2022-01-01 00:00:00',
                [
                    12,
                ],
                [
                    'deletion_version_limit' => 1,
                ],
            ],
            'Localised / Versions skips published' => [
                House::class,
                'house1',
                '2022-01-01 00:00:00',
                [
                    12,
                    11,
                    9,
                    8,
                    7,
                    5,
                    4,
                    3,
                    2,
                    1,
                ],
            ],
            'Localised / Versions keep more versions' => [
                House::class,
                'house1',
                '2022-01-01 00:00:00',
                [
                    8,
                    7,
                    5,
                    4,
                    3,
                    2,
                    1,
                ],
                [
                    'keep_limit' => 5,
                ],
            ],
            'Localised / Versions shorter lifetime' => [
                House::class,
                'house1',
                '2020-06-29 00:00:00',
                [
                    1,
                ],
                [
                    'keep_lifetime' => 179,
                ],
            ],
        ];
    }

    /**
     * @param string $class
     * @param string $id
     * @param array $versionsPreDeletion
     * @param array $versionsPostDeletion
     * @param array $tables
     * @param array $versionsToDelete
     * @throws ValidationException
     * @throws Exception
     * @dataProvider jobProcessProvider
     */
    public function testQueueDeletionJobsForVersionGroupsProcessLocalised(
        string $class,
        string $id,
        array $versionsPreDeletion,
        array $versionsPostDeletion,
        array $tables,
        array $versionsToDelete
    ): void {
        FluentState::singleton()->withState(
            function (FluentState $state) use (
                $class,
                $id,
                $versionsPreDeletion,
                $versionsPostDeletion,
                $tables,
                $versionsToDelete
            ): void {
                $state->setLocale(LocaleDefaultRecordsExtension::LOCALE_INTERNATIONAL_ENGLISH);

                $model = $this->objFromFixture($class, $id);
                $this->createTestVersions($model);

                foreach ($tables as $table) {
                    $versions = $this->getVersion($table, $model->ID);
                    $this->assertSame($versionsPreDeletion, $versions);
                }

                $job = new CleanupJob();
                $job->hydrate($versionsToDelete);
                $job->setup();
                $job->process();
                $this->assertTrue($job->jobFinished());

                foreach ($tables as $table) {
                    $versions = $this->getVersion($table, $model->ID);
                    $this->assertSame($versionsPostDeletion, $versions);
                }
            }
        );
    }

    public function jobProcessProvider(): array
    {
        return [
            'Not Localised' => [
                Ship::class,
                'ship1',
                [
                    1,
                    2,
                    3,
                    4,
                    5,
                    6,
                    7,
                    8,
                    9,
                    10,
                    11,
                    12,
                    13,
                    14,
                ],
                [
                    1,
                    2,
                    3,
                    4,
                    5,
                    7,
                    8,
                    9,
                    11,
                    12,
                    13,
                    14,
                ],
                [
                    'VersionsCleanup_Ship_Versions',
                ],
                [
                    [
                        'id' => 1,
                        'class' => Ship::class,
                        'versions' => [6, 10],
                    ],
                ],
            ],
            'Localised' => [
                House::class,
                'house1',
                [
                    1,
                    2,
                    3,
                    4,
                    5,
                    6,
                    7,
                    8,
                    9,
                    10,
                    11,
                    12,
                    13,
                    14,
                    15,
                ],
                [
                    1,
                    2,
                    3,
                    4,
                    5,
                    6,
                    7,
                    8,
                    10,
                    11,
                    12,
                    14,
                    15,
                ],
                [
                    'SiteTree_Versions',
                    'Page_Versions',
                    'VersionsCleanup_House_Versions',
                    'SiteTree_Localised_Versions',
                    'Page_Localised_Versions',
                    'VersionsCleanup_House_Localised_Versions',
                ],
                [
                    [
                        'id' => 1,
                        'class' => House::class,
                        'versions' => [9, 13],
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function testQueueDeletionJobsForVersionGroupsData(): void
    {
        $versions = [
            SiteTree::class => [
                1 => [
                    House::class => [
                        9,
                        10,
                    ],
                ],
            ],
        ];

        CleanupService::singleton()->queueDeletionJobsForVersionGroups($versions);
        $jobs = QueuedJobDescriptor::get()->filter(['Implementation' => CleanupJob::class]);
        $this->assertCount(1, $jobs);

        /** @var QueuedJobDescriptor $job */
        $job = $jobs->first();

        $jobData = unserialize($job->SavedJobData);
        $this->assertEquals(
            [
                [
                    'id' => 1,
                    'class' => House::class,
                    'versions' => [9, 10],
                ],
            ],
            $jobData->versions
        );
    }

    /**
     * @param array $versions
     * @param array $config
     * @param int $expected
     * @throws ValidationException
     * @dataProvider jobBatchSizeProvider
     */
    public function testQueueDeletionJobsForVersionGroupsBatchSize(array $versions, array $config, int $expected): void
    {
        foreach ($config as $key => $value) {
            CleanupService::config()->set($key, $value);
        }

        CleanupService::singleton()->queueDeletionJobsForVersionGroups($versions);
        $this->assertCount($expected, QueuedJobDescriptor::get()->filter(['Implementation' => CleanupJob::class]));
    }

    public function jobBatchSizeProvider(): array
    {
        return [
            'Single job' => [
                [
                    Ship::class => [
                        1 => [
                            Ship::class => [
                                2,
                                3,
                            ],
                        ],
                    ],
                    SiteTree::class => [
                        1 => [
                            House::class => [
                                9,
                                10,
                            ],
                        ],
                    ],
                ],
                [
                    'deletion_batch_size' => 0,
                ],
                1,
            ],
            'Multiple jobs' => [
                [
                    Ship::class => [
                        1 => [
                            Ship::class => [
                                2,
                                3,
                            ],
                        ],
                    ],
                    SiteTree::class => [
                        1 => [
                            House::class => [
                                9,
                                10,
                            ],
                        ],
                    ],
                ],
                [
                    'deletion_batch_size' => 1,
                ],
                2,
            ],
        ];
    }

    /**
     * @param string $class
     * @param $expected
     * @dataProvider tablesListProvider
     */
    public function testGetTablesListForClass(string $class, array $expected): void
    {
        $method = new ReflectionMethod(CleanupService::class, 'getTablesListForClass');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke(CleanupService::singleton(), $class));
    }

    public function tablesListProvider(): array
    {
        return [
            'Localised' => [
                House::class,
                [
                    'base' => [
                        'SiteTree_Versions',
                        'Page_Versions',
                        'VersionsCleanup_House_Versions',
                    ],
                    'localised' => [
                        'SiteTree_Localised_Versions',
                        'Page_Localised_Versions',
                        'VersionsCleanup_House_Localised_Versions',
                    ],
                ],
            ],
            'Not Localised' => [
                Ship::class,
                [
                    'base' => [
                        'VersionsCleanup_Ship_Versions',
                    ],
                    'localised' => [],
                ],
            ],
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

    private function getVersion(string $table, int $recordId): array
    {
        $query = SQLSelect::create(
            [
                '"Version"',
            ],
            sprintf('"%s"', $table),
            [
                '"RecordID"' => $recordId,
            ],
            [
                '"Version"' => 'ASC',
            ],
        );

        return $query->execute()->column('Version');
    }
}