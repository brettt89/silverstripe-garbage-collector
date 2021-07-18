<?php

namespace Silverstripe\GarbageCollector\Processors;

use Silverstripe\GarbageCollector\ProcessorInterface;

abstract class AbstractProcessor implements ProcessorInterface
{   
    /**
     * Identifier for expression (e.g. Base table name)
     * 
     * @var string
     */
    private $name = '';
    
    /**
     * Assign name to processor
     */
    public function __construct(string $name = '')
    {
        $this->setName($name);
    }
    
    /**
     * @return string Name of Processor
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Classes the implement this class can use this processor
     */
    abstract public static function getImplementorClass(): string;
}