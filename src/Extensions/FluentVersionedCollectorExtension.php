<?php

namespace SilverStripe\GarbageCollector\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\GarbageCollector\Collectors\VersionedCollector;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

class FluentVersionedCollectorExtension extends Extension
{
    /**
     * Modify getRecordsQuery to join with Localised tables
     * 
     * @param SQLSelect $query
     * @param string $class
     */
    public function updateGetRecordsQuery(SQLSelect $query, string $class): void
    {
        if ($this->isLocalised($class)) {
            $mainTable = $this->owner->getTableNameForClass($class);
            $baseTable = sprintf('"%s"', $this->owner->getVersionTableName($mainTable));

            $localisedTableRaw = $this->getVersionLocalisedTableName($mainTable);
            $localisedTable = sprintf('"%s"', $localisedTableRaw);
            $query
                // Join through to the localised table
                // Version numbers map one to one
                ->addInnerJoin(
                    $localisedTableRaw,
                    sprintf(
                        '%1$s."RecordID" = %2$s."RecordID" AND %1$s."Version" = %2$s."Version"',
                        $baseTable,
                        $localisedTable
                    )
                )
                ->addSelect([
                    // We will need the locale information as well later on
                    $localisedTable . '."Locale"',
                ])
                ->addGroupBy([
                    // Grouping has to be extended to locales
                    // as we want to keep the minimum number of versions per locale
                    $localisedTable . '."Locale"',
                ])
                ->addOrderBy([
                    // Extends consistent ordering to locales
                    $localisedTable . '."Locale"',
                ]);
        }
    }

    /**
     * Modify getRecords return data to include Locale data
     * 
     * @param string $class     Classname of records being modified
     * @param array  $item      Item details for returning
     * @param array  $result    Query result data
     */
    public function updateRecordsData(string $class, array $item, array $result): void
    {        
        if ($this->isLocalised($class)) {
            // Add additional locale data for localised records
            $item['locale'] = $result['Locale'];
        }
    }

    /**
     * Modify getRecords return data to include Locale data
     * 
     * @param SQLSelect $query  Select query for Versions records
     * @param string    $class  Classname of records
     * @param array     $item   Item details
     */
    public function updateGetVersionsQuery(SQLSelect $query, string $class, array $item): void
    {
        $locale = array_key_exists('locale', $item)
                    ? $item['locale']
                    : null;
        
        $mainTable = $this->owner->getTableNameForClass($class);
        $baseTable = sprintf('"%s"', $this->owner->getVersionTableName($mainTable));

        if ($locale) {
            $localisedTableRaw = $this->getVersionLocalisedTableName($mainTable);
            $localisedTable = sprintf('"%s"', $localisedTableRaw);
            $query
                // Join through to the localised table
                // Version numbers map one to one
                ->addInnerJoin(
                    $localisedTableRaw,
                    sprintf(
                        '%1$s."RecordID" = %2$s."RecordID" AND %1$s."Version" = %2$s."Version"',
                        $baseTable,
                        $localisedTable
                    )
                )
                ->addWhere([
                    // Narrow down the search to specific locale, this ensures that we keep minimum
                    // required versions per locale
                    $localisedTable . '."Locale"' => $locale,
                ]);
        }

    }

    /**
     * Modify getRecords return data to include Locale data
     * 
     * @param SQLDelete $query  Delete query for Version records
     * @param string    $class  Classname of records
     * @param array     $item   Item details
     */
    public function updateDeleteVersionsQuery(SQLDelete $query, string $class, array $item): void
    {
        $tables = $this->owner->getTablesListForClass($class);
        $baseTables = $tables['base'];
        $localisedTables = $tables['localised'];

        // Add localised table to the join and deletion
        if (count($localisedTables) > 0) {
            $localisedTablesRaw = $localisedTables;

            array_walk($localisedTables, static function (&$item): void {
                $item = sprintf('"%s"', $item);
            });

            // Register localised tables for deletion so we delete records from it
            $query->addDelete($localisedTables);

            foreach ($localisedTablesRaw as $table) {
                $query->addLeftJoin(
                    $table,
                    sprintf(
                        '%1$s."RecordID" = "%2$s"."RecordID" AND %1$s."Version" = "%2$s"."Version"',
                        $baseTable,
                        $table
                    )
                );
            }
        }
    }

    /**
     * Modify getRecords return data to include Locale data
     * 
     * @param string $class   Classname of records
     * @param array  $tables  Tables list data for class
     */
    public function updateTablesListForClass(string $class, array $tables)
    {
        // Include localised tables if needed
        if ($singleton->hasExtension(FluentVersionedExtension::class)) {
            $localisedTables = [];
            $localisedDataTables = array_keys($singleton->getLocalisedTables());

            foreach ($tables as $table) {
                if (!in_array($table, $localisedDataTables)) {
                    // Skip any tables that do not contain localised data
                    continue;
                }

                $localisedTables[] = $this->getVersionLocalisedTableName($table);
            }

            // Add localised Table data to Tables List
            $tables['localised'] = $localisedTables;
        }
    }

    /**
     * Check if Class is Localised (Has Fluent extension and Localised fields)
     * 
     * @param string $class
     * @return bool
     */
    protected function isLocalised(string $class): bool
    {
        /** @var DataObject|FluentExtension $singleton */
        $singleton = DataObject::singleton($class);
        return $singleton->hasExtension(FluentVersionedExtension::class)
            && count($singleton->getLocalisedFields()) > 0;
    }

    /**
     * Determine the name of localised version table
     *
     * @param string $table
     * @return string
     */
    protected function getVersionLocalisedTableName(string $table): string
    {
        return $table . '_Localised_Versions';
    }
}