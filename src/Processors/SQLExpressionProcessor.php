<?php

namespace SilverStripe\GarbageCollector\Processors;

use SilverStripe\ORM\Queries\SQLExpression;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\DB;

class SQLExpressionProcessor extends AbstractProcessor
{

    /**
     * Expression to delete
     *
     * @var SQLExpression
     */
    private $expression;
    
    public function __construct(SQLExpression $expression, string $name = '')
    {
        $this->expression = $expression;
        parent::__construct($name);
    }
    
    /**
     * Execute deletion of records
     *
     * @return int Number of records deleted
     */
    public function process(): int
    {
        // Create SQLDelete statement from SQL provided and execute
        $delete = $this->expression->toDelete();
        $delete->execute();

        return DB::affected_rows();
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
        
        // Use the 'Base Table' of the query as the Classname for Name
        $from = $this->expression->getFrom();
        if (!empty($from) && is_array($from) && count($from) > 0) {
            $this->setName(array_shift($from));
        } else {
            $this->setName('UnknownName');
        }

        return parent::getName();
    }

    /**
     * Classes the implement this class can use this processor
     */
    public static function getImplementorClass(): string
    {
        return SQLExpression::class;
    }
}
