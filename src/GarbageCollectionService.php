<?php

namespace Silverstripe\GarbageCollection;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class GarbageCollectionService
{
    use Configurable;
    use Injectable;

    /**
     * @internal
     * @var self
     */
    private static $instance;
    
    /**
     * Collectors registered for processing
     * 
     * @var array
     */
    private static $collectors = [];

    /**
     * @return self
     */
    public static function inst()
    {
        return self::$instance ? self::$instance : self::$instance = new static();
    }

    /**
     * Array of collectors for processing
     * 
     * @var array
     */
    public function getCollectors(): array
    {
        return $this->config()->get('collectors');
    }
}