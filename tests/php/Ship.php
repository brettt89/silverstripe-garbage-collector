<?php

namespace SilverStripe\GarbageCollector\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Ship extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'GarbageCollector_Ship';

    /**
     * @var string[]
     */
    private static $db = [
        'Title' => 'Varchar',
    ];
}
