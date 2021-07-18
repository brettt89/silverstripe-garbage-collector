<?php

namespace Silverstripe\GarbageCollector\Collectors;

use Silverstripe\GarbageCollector\CollectorInterface;

abstract class AbstractCollector implements CollectorInterface
{
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
        return $this->config()->get('processors');
    }
}