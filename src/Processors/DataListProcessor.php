<?php

namespace SilverStripe\GarbageCollector\Processors;

use SilverStripe\ORM\DataList;

class DataListProcessor extends AbstractProcessor
{

    /**
     * DataObject to delete
     *
     * @var DataList
     */
    private $list;
    
    public function __construct(DataList $list, string $name = '')
    {
        $this->list = $list;
        parent::__construct($name);
    }
    
    /**
     * Execute deletion of records
     *
     * @return int Number of records deleted
     */
    public function process(): int
    {
        $count = 0;
        // Create SQLDelete statement from SQL provided and execute
        foreach ($this->list as $object) {
            $object->delete();
            $count++;
        }

        // Only a single item was processed
        return $count;
    }

    /**
     * Get name of processor
     *
     * @return string Name of processor
     */
    public function getName(): string
    {
        if ($name = parent::getName()) {
            return $name;
        }
        
        return $this->list->dataClass;
    }

    /**
     * Classes the implement this class can use this processor
     */
    public static function getImplementorClass(): string
    {
        return DataList::class;
    }
}
