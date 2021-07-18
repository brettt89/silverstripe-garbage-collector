<?php

namespace SilverStripe\GarbageCollector;

use SilverStripe\ORM\Queries\SQLConditionalExpression;

interface CollectorInterface
{
    /**
     * @return string
     */
    public function getName(): string;
    
    /**
     * @return array
     */
    public function getCollections(): array;

    /**
     * @return string[] Array of Processor Classes to be initiated with args
     */
    public function getProcessors(): array;
}