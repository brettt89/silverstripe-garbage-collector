<?php

namespace SilverStripe\GarbageCollector\Collectors;

use SilverStripe\GarbageCollector\Processors\SQLExpressionProcessor;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;

class ChangeSetCollector extends AbstractCollector
{
    /**
     * ChangeSet lifetime in days
     * Any records older than this duration are considered obsolete and ready for deletion
     */
    private static $deletion_lifetime = 100;

    /**
     * Number of ChangeSet records to be deleted in one go
     * Note that this does directly impact number of related records so the actual number of deleted records may vary
     */
    private static $deletion_limit = 100;

    /**
     * Query limit for batching
     *
     * @config
     * @var integer
     */
    private static $query_limit = 5;

    /**
     * Processors used for processing items
     *
     * @var array
     */
    private static $processors = [
        SQLExpressionProcessor::class
    ];

    public function getName(): string
    {
        return 'ChangeSetCollector';
    }

    public function getCollections(): array
    {
        $collection = [];

        $ids = $this->getChangeSetIDs();
        if (empty($ids)) {
            return $collection;
        }

        do {
            $batch = array_splice($ids, 0, $this->config()->get('deletion_limit'));
            $collection[] = $this->getSQLQueryRecord($batch);
        } while (!empty($ids) && count($collection) <= $this->config()->get('query_limit'));


        return $collection;
    }

    /**
     * Get ChangeSet Id's ready for deletion
     *
     * @return array
     */
    private function getChangeSetIDs()
    {
        $deletionDate = DBDatetime::now()
            ->modify(sprintf('- %d days', $this->config()->get('deletion_lifetime')))
            ->Rfc2822();

        $dataList = ChangeSet::get()
            ->filter(['LastEdited:LessThan' => $deletionDate])
            ->sort('ID', 'ASC')
            ->limit($this->config()->get('deletion_limit') * $this->config()->get('query_limit'));

        return $dataList->columnUnique('ID');
    }

    private function getSQLQueryRecord(array $ids)
    {
        $mainTableRaw = ChangeSet::config()->get('table_name');
        $itemTableRaw = ChangeSetItem::config()->get('table_name');
        $relationTableRaw = $itemTableRaw . '_ReferencedBy';
        $mainTable = sprintf('"%s"', $mainTableRaw);
        $itemTable = sprintf('"%s"', $itemTableRaw);
        $relationTable = sprintf('"%s"', $relationTableRaw);

        $query = SQLDelete::create(
            [
                $mainTable,
            ],
            [
                sprintf($mainTable . '."ID" IN (%s)', DB::placeholders($ids)) => $ids,
            ],
            [
                $mainTable,
                $itemTable,
                $relationTable,
            ]
        );

        $query->addLeftJoin($itemTableRaw, sprintf('%s."ID" = %s."ChangeSetID"', $mainTable, $itemTable));
        $query->addLeftJoin($relationTableRaw, sprintf('%s."ID" = %s."ChangeSetItemID"', $itemTable, $relationTable));

        return $query;
    }
}
