<?php

namespace SilverStripe\GarbageCollector\Collectors;

use SilverStripe\GarbageCollector\CollectorInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Config\Config;

abstract class AbstractCollector implements CollectorInterface
{
    use Configurable;

    /**
     * Processors used for processing items
     *
     * @var array
     */
    private static $processors = [];

    /**
     * @return string Name of Collector
     */
    abstract public function getName(): string;

    /**
     * @return array Collection data
     */
    abstract public function getCollections(): array;

    /**
     * Return an array of ClassNames to be created
     *
     *   ClassNames are used here instead of instantiated objects as
     *   collections are passed to constructor.
     *
     * @return string[] array of ClassNames of processors
     */
    public function getProcessors(): array
    {
        return $this->config()->get('processors', Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED);
    }
}
