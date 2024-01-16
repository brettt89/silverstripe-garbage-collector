<?php

namespace SilverStripe\GarbageCollector\Processors;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLConditionalExpression;

class SQLExpressionProcessor extends AbstractProcessor
{

    /**
     * Expression to delete
     *
     * @var SQLConditionalExpression
     */
    private $expression;

    public function __construct(SQLConditionalExpression $expression = null, string $name = '')
    {
        $this->expression = $expression;
        parent::__construct($name);
    }

    /**
     * Get internal SQL expression
     *
     * @return SQLConditionalExpression
     * @throws \Exception
     */
    protected function getExpression(): SQLConditionalExpression
    {
        if (!is_a($this->expression, SQLConditionalExpression::class)) {
            throw new \Exception(static::class . ' requires a SQLConditionalExpression provided via its constructor.');
        }

        return $this->expression;
    }

    /**
     * Execute deletion of records
     *
     * @return int Number of records deleted
     * @throws \Exception
     */
    public function process(): int
    {
        // Create SQLDelete statement from SQL provided and execute
        $delete = $this->getExpression()->toDelete();

        $delete->execute();

        return DB::affected_rows();
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

        // Use the 'Base Table' of the query as the Classname for Name
        $from = $this->getExpression()->getFrom();
        if (!empty($from) && is_array($from) && count($from) > 0) {
            $this->setName(trim(array_shift($from), '"'));
        } else {
            $this->setName('UnknownName');
        }

        return parent::getName();
    }

    /**
     * Classes the implement this class can use this processor
     */
    public function getImplementorClass(): string
    {
        return SQLConditionalExpression::class;
    }
}
