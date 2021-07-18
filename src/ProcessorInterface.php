<?php

namespace SilverStripe\GarbageCollector;

interface ProcessorInterface
{   
    /**
     * @return string Name for logging of processor
     */
    public function getName(): string;

    /**
     * @return string ImplementorClass
     */
    public static function getImplementorClass(): string;

    /**
     * @return int Number of processed records
     */
    public function process(): int;
}