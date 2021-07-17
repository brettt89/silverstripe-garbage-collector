<?php

namespace Silverstripe\GarbageCollection;

interface ProcessorInterface
{
    public function getName(): string;

    public static function getImplementorClass(): string;

    public function process(): int;
}