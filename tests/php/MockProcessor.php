<?php

namespace SilverStripe\GarbageCollector\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\GarbageCollector\ProcessorInterface;
use SilverStripe\GarbageCollector\Tests\Ship;

class MockProcessor implements ProcessorInterface, TestOnly
{
    private $return = 0;

    public function __construct($return = 0)
    {
        $this->return = is_int($return) ? $return : (is_array($return) ? count($return) : 1);
    }

    public function getName(): string
    {
        return 'MockProcessor';
    }

    public function getImplementorClass(): string
    {
        return Ship::class;
    }

    public function process(): int
    {
        return $this->return;
    }
}
