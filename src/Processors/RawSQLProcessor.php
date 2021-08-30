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

    public function __construct(RawSQL $query = null, string $name = '')
    {
        $this->query = $query;
        parent::__construct($name);
    }

    /**
     * Get internal query
     *
     * @return RawSQL
     * @throws \Exception
     */
    protected function getQuery(): RawSQL
    {
        if (!is_a($this->query, RawSQL::class)) {
            throw new \Exception(static::class . ' requires a RawSQL provided via its constructor.');
        }

        return $this->query;
    }

    /**
     * Execute query
     *
     * @return int Always 1 as its a single SQL query being executed
     * @throws \Exception
     */
    public function process(): int
    {
        DB::query($this->getQuery()->sql());
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
