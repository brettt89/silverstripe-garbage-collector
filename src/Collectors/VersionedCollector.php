<?php

namespace SilverStripe\GarbageCollector\Collectors;

use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\GarbageCollector\Processors\SQLExpressionProcessor;
use SilverStripe\ORM\Connect\Query;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLExpression;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;

class VersionedCollector extends AbstractCollector
{
    use Configurable;
    use Extensible;

    /**
     * Number of latest versions which will always be kept
     *
     * @var int
     */
    private static $keep_limit = 2;

    /**
     * Delete only versions older than this limit in days
     *
     * @var int
     */
    private static $keep_lifetime = 180;

    /**
     * Determine whether to keep unpublished draft versions (newer than latest published version)
     *
     * @var bool
     */
    private static $keep_unpublished_drafts = false;

    /**
     * Determine whether to delete published records
     *
     * @var bool
     */
    private static $delete_published_records = false;

    /**
     * Determine whether to delete published versions
     *
     * @var bool
     */
    private static $delete_published_versions = false;

    /**
     * Number of records processed in one deletion run per base class
     *
     * @var int
     */
    private static $deletion_record_limit = 100;

    /**
     * Number of versions deleted in one deletion run per record
     *
     * @var int
     */
    private static $deletion_version_limit = 100;

    /**
     * Query limit for batching
     *
     * @config
     * @var integer
     */
    private static $query_limit = 10;

    /**
     * @var array
     */
    private static $base_classes = [];

    /**
     * Processors used for processing items
     *
     * @var array
     */
    private static $processors = [
        SQLExpressionProcessor::class,
    ];

    public function getName(): string
    {
        return 'VersionedCollector';
    }

    /**
     * Returns array of SQL statements for deletion
     *
     * @return array
     */
    public function getCollections(): array
    {
        // Format data into job specific format so it's easy to consume
        $collections = [];

        $classes = $this->getBaseClasses();

        foreach ($classes as $class) {
            // Process only one class at a time so we don't exhaust memory
            $records = $this->getRecordsForDeletion([$class]);
            $versionData = $this->getVersionsForDeletion($records);

            if (empty($versionData)) {
                continue;
            }

            foreach ($versionData as $records) {
                foreach ($records as $recordId => $recordData) {
                    foreach ($recordData as $class => $versions) {
                        if (empty($versions)) {
                            return $collections;
                        }

                        do {
                            $batch = array_splice($versions, 0, $this->config()->get('deletion_version_limit'));
                            $query = $this->deleteVersionsQuery($class, $recordId, $batch);

                            if ($query) {
                                $collections[] = $query;
                            }
                        } while (!empty($versions) && count($collections) <= $this->config()->get('query_limit'));
                    }
                }
            }
        }

        return $collections;
    }

    /**
     * Get list of valid base classes that we need to process
     *
     * @return array
     */
    protected function getBaseClasses(): array
    {
        $classes = $this->config()->get('base_classes');

        return array_filter($classes, static function ($class) {
            $singleton = DataObject::singleton($class);

            if (!$singleton->hasExtension(Versioned::class)) {
                // Skip non-versioned classes as there are no old version records to delete
                return false;
            }

            if ($class !== $singleton->baseClass()) {
                // Skip non-base-class as subclasses are covered automatically
                return false;
            }

            return true;
        });
    }

    /**
     * Determine which records have versions that can be deleted
     *
     * @param array $classes
     * @return array
     */
    protected function getRecordsForDeletion(array $classes): array
    {
        $keepLimit = (int) $this->config()->get('keep_limit');
        $recordLimit = (int) $this->config()->get('deletion_record_limit');
        $keepUnpublishedDrafts = (bool) $this->config()->get('keep_unpublished_drafts');
        $deletePublishedRecords = (bool) $this->config()->get('delete_published_records');
        $deletionDate = DBDatetime::create_field('Datetime', DBDatetime::now()->Rfc2822());
        $deletionDate = $deletionDate->setValue(strtotime(
            sprintf('- %d days', $this->config()->get('keep_lifetime')),
            $deletionDate->getTimestamp()
        ))->Rfc2822();
        $records = [];

        foreach ($classes as $class) {
            $mainTable = $this->getTableNameForClass($class);
            $baseTableRaw = $this->getVersionTableName($mainTable);
            $baseTable = sprintf('"%s"', $baseTableRaw);
            $query = SQLSelect::create(
                [
                    // We need to identify the records which have old versions ready for deletion
                    $baseTable . '."RecordID"',
                ],
                $baseTable,
                [
                    // Include only versions older than specified date
                    $baseTable . '."LastEdited" <= ?' => $deletionDate,
                    // Include published records if delete_published_records is set to true
                    $baseTable . '."WasPublished"' => $deletePublishedRecords,
                    // Skip records without mandatory data
                    $baseTable . '."ClassName" IS NOT NULL',
                    $baseTable . '."ClassName" != ?' => '',
                ],
                [
                    // Apply consistent ordering
                    $baseTable . '."RecordID"' => 'ASC',
                ],
                [
                    // Grouping by Record ID as we want to get RecordID overview at this point
                    $baseTable . '."RecordID"',
                ],
                [
                    // Need to have more old versions than the allowed limit
                    'COUNT(1) > ?' => $keepLimit,
                ],
                $recordLimit
            );

            if ($keepUnpublishedDrafts) {
                $query->addInnerJoin(
                    // table
                    '(SELECT "RecordID", MAX("Version") as MaxPublishedVersion FROM ' . $baseTable . ' WHERE "WasPublished" = 1 GROUP BY RecordID)',
                    // on predicate
                    $baseTable . '."RecordID" = "MaxVersionSelect"."RecordID"',
                    // table alias
                    'MaxVersionSelect'
                );
                $query->addWhere($baseTable . '."Version" < "MaxVersionSelect"."MaxPublishedVersion"');
            }

            $this->extend('updateGetRecordsQuery', $query, $class);

            $results = $query->execute();

            if ($results === null) {
                continue;
            }

            // Process results
            $data = $this->processResults($class, $results);

            if (count($data) === 0) {
                continue;
            }

            $records[$class] = $data;
        }

        return $records;
    }

    /**
     * Determine which versions need to be deleted for specified records
     *
     * @param array $records
     * @return array
     */
    protected function getVersionsForDeletion(array $records): array
    {
        $keepLimit = (int) $this->config()->get('keep_limit');
        $versionLimit = (int) $this->config()->get('deletion_version_limit') * $this->config()->get('query_limit');
        $keepUnpublishedDrafts = (bool) $this->config()->get('keep_unpublished_drafts');
        $deletePublishedVersions = (bool) $this->config()->get('delete_published_versions');
        $deletionDate = DBDatetime::create_field('Datetime', DBDatetime::now()->Rfc2822());
        $deletionDate = $deletionDate->setValue(strtotime(
            sprintf('- %d days', $this->config()->get('keep_lifetime')),
            $deletionDate->getTimestamp()
        ))->Rfc2822();
        $versions = [];

        foreach ($records as $baseClass => $items) {
            $mainTable = $this->getTableNameForClass($baseClass);
            $baseTableRaw = $this->getVersionTableName($mainTable);
            $baseTable = sprintf('"%s"', $baseTableRaw);

            foreach ($items as $item) {
                $recordId = $item['id'];
                $query = SQLSelect::create(
                    [
                        // We need version number so we can delete it
                        $baseTable . '."Version"',
                        // We need class name as this drives which tables need to be joined during deletion
                        $baseTable . '."ClassName"',
                    ],
                    $baseTable,
                    [
                        $baseTable . '."RecordID"' => $recordId,
                        // Include only versions older than specified date
                        $baseTable . '."LastEdited" <= ?' => $deletionDate,
                        // Include published versions if delete_published is set to true
                        $baseTable . '."WasPublished"' => $deletePublishedVersions,
                        // Skip records without mandatory data
                        $baseTable . '."ClassName" IS NOT NULL',
                        $baseTable . '."ClassName" != ?' => '',
                    ],
                    [
                        // Latest versions first so we can keep the minimum required versions easily
                        // This will cause the newer versions to be deleted first but it shouldn't matter
                        // as all old versions will get deleted eventually (order shouldn't matter)
                        $baseTable . '."Version"' => 'DESC',
                    ],
                    [],
                    [],
                    [
                        // Make sure we skip the versions which need to be retained
                        // these will be at the start of the list because of our sorting order
                        'limit' => $versionLimit,
                        'start' => $keepLimit,
                    ]
                );

                if ($keepUnpublishedDrafts) {
                    $query->addInnerJoin(
                        // table
                        '(SELECT "RecordID", MAX("Version") as MaxPublishedVersion FROM ' . $baseTable . ' WHERE "WasPublished" = 1 GROUP BY RecordID)',
                        // on predicate
                        $baseTable . '."RecordID" = "MaxVersionSelect"."RecordID"',
                        // table alias
                        'MaxVersionSelect'
                    );
                    $query->addWhere($baseTable . '."Version" < "MaxVersionSelect"."MaxPublishedVersion"');
                }

                $this->extend('updateGetVersionsQuery', $query, $baseClass, $item);

                $results = $query->execute();

                if ($results === null) {
                    continue;
                }

                // Group versions by class so it's easier to process them later
                $data = $this->groupVersions($results);

                if (count($data) === 0) {
                    continue;
                }

                $versions[$baseClass][$recordId] = $data;
            }
        }

        return $versions;
    }

    /**
     * Execute deletion of specified versions for a record
     *
     * @param string $class
     * @param int $recordId
     * @param array $versions
     * @return SQLExpression|null
     */
    protected function deleteVersionsQuery(string $class, int $recordId, array $versions): ?SQLExpression
    {
        if (count($versions) === 0) {
            // Nothing to delete
            return null;
        }

        $tables = $this->getTablesListForClass($class);
        $baseTables = $tables['base'];

        if (count($baseTables) === 0) {
            return null;
        }

        // We can assume first table is the base table
        $baseTableRaw = $baseTables[0];
        $baseTablesRaw = $baseTables;

        $baseTable = sprintf('"%s"', $baseTableRaw);
        array_walk($baseTables, static function (&$item): void {
            $item = sprintf('"%s"', $item);
        });

        $query = SQLDelete::create(
            [
                $baseTable,
            ],
            [
                // We are deleting specific versions for specific record
                $baseTable . '."RecordID"' => $recordId,
                sprintf($baseTable . '."Version" IN (%s)', DB::placeholders($versions)) => $versions,
            ],
            $baseTables
        );

        // Join additional tables so we can delete all related data (avoid orphaned version data)
        foreach ($baseTablesRaw as $table) {
            // No need to join the base table as it's already present in the FROM
            if ($table === $baseTableRaw) {
                continue;
            }

            $query->addLeftJoin(
                $table,
                sprintf('%1$s."RecordID" = "%2$s"."RecordID" AND %1$s."Version" = "%2$s"."Version"', $baseTable, $table)
            );
        }

        $this->extend('updateDeleteVersionsQuery', $query, $class);

        return $query;
    }

    /**
     * Get list of all tables that the specified class has
     *
     * @param string $class
     * @return array[]
     */
    public function getTablesListForClass(string $class): array
    {
        $classes = ClassInfo::ancestry($class, true);
        $tables = [];

        foreach ($classes as $currentClass) {
            $tables[] = $this->getTableNameForClass($currentClass);
        }

        $baseTables = [];
        foreach ($tables as $table) {
            $baseTables[] = $this->getVersionTableName($table);
        }

        $return['base'] = $baseTables;

        $this->extend('updateTablesListForClass', $class, $tables, $return);

        return $return;
    }

    /**
     * Determine name of table from class
     *
     * @param string $class
     * @return string
     */
    public function getTableNameForClass(string $class): string
    {
        $table = DataObject::getSchema()->tableName($class);

        // Fallback to class name if no table name is specified
        return $table ?: $class;
    }

    /**
     * Determine the name of version table
     *
     * @param string $table
     * @return string
     */
    public function getVersionTableName(string $table): string
    {
        return $table . '_Versions';
    }

    /**
     * Process a list of results
     *
     * @param string $class
     * @param Query $results
     * @return array
     */
    private function processResults(string $class, Query $results): array
    {
        $data = [];

        if (method_exists($results, 'next')) {
            // Process for CMS 4.13
            while ($result = $results->next()) {
                $item = $this->processResult($class, $result);
                $data[] = $item;
            }
        } elseif (method_exists($results, 'getIterator')) {
            // Process for CMS 5+
            foreach ($results as $result) {
                $item = $this->processResult($class, $result);
                $data[] = $item;
            }
        } else {
            // Handle unsupported results object, just in case
            throw new Exception('The results object does not support next method or Traversable interface.');
        }

        return $data;
    }

    /**
     * Process a single result item.
     *
     * @param string $class
     * @param array $result
     * @return array
     */
    private function processResult(string $class, array $result): array
    {
        $item = [
            'id' => (int) $result['RecordID'],
        ];

        $this->extend('updateRecordsData', $class, $item, $result);

        return $item;
    }

    /**
     * Group versions by class so it's easier to process them later
     *
     * @param Query $results
     * @return array
     */
    private function groupVersions(Query $results): array
    {
        $data = [];

        if (method_exists($results, 'next')) {
            // Process for CMS 4.13
            while ($result = $results->next()) {
                $class = $result['ClassName'];
                $version = (int) $result['Version'];
                $data[$class][] = $version;
            }
        } elseif (method_exists($results, 'getIterator')) {
            // Process for CMS 5+
            foreach ($results as $result) {
                $class = $result['ClassName'];
                $version = (int) $result['Version'];
                $data[$class][] = $version;
            }
        } else {
            // Handle unsupported results object, just in case
            throw new Exception('The results object does not support next method or Traversable interface.');
        }

        // reverse the order of versions so we delete the oldest first
        return array_map('array_reverse', $data);
    }
}
