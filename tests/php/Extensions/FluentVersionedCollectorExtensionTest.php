<?php

namespace SilverStripe\GarbageCollector\Tests\Extensions;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\GarbageCollector\Tests\Ship;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

class FluentVersionedCollectorExtensionTest extends SapphireTest
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
    ];

    public function testModifyRecordsExtension()
    {

    }
}