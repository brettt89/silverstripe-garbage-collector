<?php

namespace SilverStripe\GarbageCollector\Tests;

use SilverStripe\Dev\TestOnly;

class CargoShip extends Ship implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'GarbageCollector_CargoShip';

    /**
     * @var string[]
     */
    private static $db = [
        'Title' => 'Varchar',
    ];
}
