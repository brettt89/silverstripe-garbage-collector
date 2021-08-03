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

    public function __construct(DataList $list = null, string $name = '')
    {
        $this->list = $list;
        parent::__construct($name);
    }

    /**
     * Get internal datalist
     *
     * @return DataList
     * @throws \Exception
     */
    protected function getList(): DataList
    {
        if (!is_a($this->list, DataList::class)) {
            throw new \Exception(static::class . ' requires a DataList provided via its constructor.');
        }

        return $this->list;
    }

    /**
     * Execute deletion of records
     *
     * @return int Number of records deleted
     * @throws \Exception
     */
    public function process(): int
    {
        $count = 0;
        // Create SQLDelete statement from SQL provided and execute
        foreach ($this->getList() as $object) {
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
     * @throws \Exception
     */
    public function getName(): string
    {
        if ($name = parent::getName()) {
            return $name;
        }

        return $this->getList()->dataClass;
    }

    /**
     * Classes the implement this class can use this processor
     */
    public function getImplementorClass(): string
    {
        return DataList::class;
    }
}
