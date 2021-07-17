<?php

namespace Silverstripe\GarbageCollection;

use SilverStripe\ORM\Queries\SQLConditionalExpression;

interface CollectorInterface
{
    public function getName(): string;
    
    public function getCollections(): array;

    public function getProcessors(): array;
}