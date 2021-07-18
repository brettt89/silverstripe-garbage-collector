<?php

namespace SilverStripe\GarbageCollector;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;

class GarbageCollectorService
{
    use Configurable;

    /**
     * @internal
     * @var self
     */
    private static $instance;
    
    /**
     * Collectors registered for processing
     * 
     * @var string[] Array of ClassNames for collectors to process
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
     * @return CollectorInterface[] Array of Collectors
     */
    public function getCollectors(): array
    {
        $collectors = [];
        
        foreach ($this->config()->get('collectors') as $collector) {
            $collectors[] = Injector::inst()->get($collector);
        }

        return $collectors;
    }
}