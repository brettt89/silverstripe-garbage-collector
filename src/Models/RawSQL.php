<?php

namespace SilverStripe\GarbageCollector\Models;

class RawSQL
{
    private $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function sql()
    {
        return $this->getQuery();
    }
}
