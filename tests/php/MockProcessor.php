<?php

namespace SilverStripe\GarbageCollector\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\GarbageCollector\ProcessorInterface;
use SilverStripe\GarbageCollector\Tests\Ship;

class MockProcessor implements ProcessorInterface, TestOnly
{
    private $return;
    
    public function __construct($item)
    {
        $this->return = 4;
    }
    
    public function getName(): string
    {
        return 'MockProcessor';
    }
    
    public static function getImplementorClass(): string
    {
        return Ship::class;
    }
    
    public function process(): int
    {
        return $this->return;
    }

    public function returnValue($return)
    {
        $this->return = $return;
    }
}
