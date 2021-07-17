<?php

namespace Silverstripe\GarbageCollection\Processors;

use Silverstripe\GarbageCollection\ProcessorInterface;
use SilverStripe\ORM\Queries\SQLExpression;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\DB;

class SQLExpressionProcessor implements ProcessorInterface
{   
    /**
     * Expression to delete
     * 
     * @var SQLExpression
     */
    private $expression;

    /**
     * Identifier for expression (e.g. Base table name)
     * 
     * @var string
     */
    private $name;
    
    public function __construct(SQLExpression $expression, string $name = null)
    {
        $this->expression = $expression;
        $this->name = $name;
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
        if (isset($this->name) && !empty($this->name)) {
            return $this->name;
        }
        
        // Use the 'Base Table' of the query as the Classname for Name
        $from = $this->expression->getFrom();
        if (!empty($from) && is_array($from) && count($from) > 0) {
            $this->name = array_shift($from);
        } else {
            $this->name = 'UnknownName';
        }

        return $this->name;
    }

    /**
     * Classes the implement this class can use this processor
     */
    public static function getImplementorClass(): string
    {
        return SQLExpression::class;
    }
}