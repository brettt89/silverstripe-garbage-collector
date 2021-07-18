<?php

namespace Silverstripe\GarbageCollection\Collectors;

use Silverstripe\GarbageCollection\CollectorInterface;

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
     * @return string[] array of ClassNames of processors
     */
    public function getProcessors(): array
    {
        return $this->config()->get('processors');
    }
}