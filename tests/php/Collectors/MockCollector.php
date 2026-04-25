<?php

namespace SilverStripe\GarbageCollector\Tests\Collectors;

use SilverStripe\Dev\TestOnly;
use SilverStripe\GarbageCollector\Collectors\AbstractCollector;
use SilverStripe\GarbageCollector\Tests\MockProcessor;

class MockCollector extends AbstractCollector implements TestOnly
{
    private static $processors = [
        MockProcessor::class
    ];

    public function getName(): string
    {
        return 'MockCollector';
    }

    public function getCollections(): array
    {
        return [];
    }
}
