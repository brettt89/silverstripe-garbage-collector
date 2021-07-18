<?php

namespace Silverstripe\GarbageCollection;

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
     * @return ProcessorInterface[]
     */
    public function getProcessors(): array;
}