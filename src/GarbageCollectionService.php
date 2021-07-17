<?php

namespace Silverstripe\GarbageCollection;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class GarbageCollectionService
{
    use Configurable;
    use Injectable;
    
    /**
     * Collectors registered for processing
     * 
     * @var array
     */
    private static $collectors = [];

    /**
     * Array of collectors for processing
     * 
     * @var array
     */
    public static function getCollectors(): array
    {
        return $this->config()->get('collectors');
    }
}