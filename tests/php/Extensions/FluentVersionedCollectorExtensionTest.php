<?php

namespace SilverStripe\GarbageCollector\Tests\Extensions;

use SilverStripe\GarbageCollector\Collectors\VersionedCollector;
use SilverStripe\GarbageCollector\Extensions\FluentVersionedCollectorExtension;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\State\FluentState;

use SilverStripe\GarbageCollector\Tests\Ship;
use SilverStripe\GarbageCollector\Tests\Collectors\VersionedCollectorTest;


class FluentVersionedCollectorExtensionTest extends VersionedCollectorTest
{
    /**
     * @var string
     */
    protected static $fixture_file = [
        'tests/php/Models.yml',
        'tests/php/Fluent.yml'
    ];

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
            FluentVersionedExtension::class
        ],
        VersionedCollector::class => [
            FluentVersionedCollectorExtension::class
        ]
    ];

    protected function setUp(): void
    {
        FluentState::singleton()->withState(function (FluentState $state): void {
            $state->setLocale('en_GB');
            parent::setUp();
        });
    }

    /**
     * @param string $id
     * @param ?string $modifyDate
     * @param array $expected
     * @param ?int $deletion_limit
     * @param ?int $keep_limit
     * @param bool $keep_unpublished_drafts
     * @throws ValidationException
     * @dataProvider collectionsProvider
     */
    public function testGetCollections(
        string $id,
        string $modifyDate = null,
        array $expected = [],
        int $deletion_limit = null,
        int $keep_limit = null,
        bool $keep_unpublished_drafts = false
    ): void
    {
        FluentState::singleton()->withState(function (FluentState $state) use ($id, $modifyDate, $expected, $deletion_limit): void {
            $state->setLocale('en_GB');
            parent::testGetCollections($id, $modifyDate, $expected, $deletion_limit);
        });
    }

    public function collectionsProvider(): array
    {
        return [
            'Localised / No versions passed lifetime' => [
                'ship1'
            ],
            'Localised / Versions passed lifetime' => [
                'ship2',
                '+ 184 days',
                [
                    [
                        'recordId' => 2,
                        'versionIds' => [ 3, 4 ],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"',
                            '"GarbageCollector_Ship_Localised_Versions"'
                        ]
                    ]
                ]
            ],
            'Localised / Versions passed lifetime, Multi Query' => [
                'ship3',
                '+ 184 days',
                [
                    [
                        'recordId' => 3,
                        'versionIds' => [ 3 ],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"',
                            '"GarbageCollector_Ship_Localised_Versions"'
                        ]
                    ],
                    [
                        'recordId' => 3,
                        'versionIds' => [ 4 ],
                        'tables' => [
                            '"GarbageCollector_Ship_Versions"',
                            '"GarbageCollector_Ship_Localised_Versions"'
                        ]
                    ]
                ],
                1
            ]
        ];
    }
}
