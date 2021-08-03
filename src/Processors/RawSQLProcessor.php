<?php

namespace SilverStripe\GarbageCollector\Processors;

use SilverStripe\GarbageCollector\Models\RawSQL;
use SilverStripe\ORM\DB;

class RawSQLProcessor extends AbstractProcessor
{

    /**
     * Query to process
     *
     * @var RawSQL
     */
    private $query;
    
    public function __construct(RawSQL $query, string $name = '')
    {
        $this->query = $query;
        parent::__construct($name);
    }
    
    /**
     * Execute query
     *
     * @return int Always 1 as its a single SQL query being executed
     */
    public function process(): int
    {
        DB::query($this->query->sql());
        return 1;
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

        return 'RawSQLProcessor';
    }

    /**
     * Classes the implement this class can use this processor
     */
    public function getImplementorClass(): string
    {
        return RawSQL::class;
    }
}
