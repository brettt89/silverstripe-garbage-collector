<?php

namespace Silverstripe\GarbageCollector\Tests;

use Silverstripe\GarbageCollector\ProcessorInterface;
use Silverstripe\GarbageCollector\Tests\Ship;

class MockProcessor implements ProcessorInterface
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