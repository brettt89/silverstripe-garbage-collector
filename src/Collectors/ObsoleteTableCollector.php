<?php

namespace SilverStripe\GarbageCollector\Collectors;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DB;
use SilverStripe\GarbageCollector\Processors\RawSQLProcessor;
use SilverStripe\GarbageCollector\Models\RawSQL;

class ObsoleteTableCollector extends AbstractCollector
{
    /**
     * Array of tables to skip
     * E.g. ['SiteTree'] will skip deletion of any `_obsolete_SiteTree` tables.
     *
     * @config
     * @var array
     */
    private static $skip_tables = [];

    /**
     * Obsolete table prefix used for identifying obsolete tables
     *
     * @config
     * @var string
     */
    private static $table_prefix = '_obsolete_';

    /**
     * Processors used for processing items
     *
     * @config
     * @var array
     */
    private static $processors = [
        RawSQLProcessor::class
    ];

    public function getName(): string
    {
        return 'ObsoleteTableCollector';
    }

    public function getCollections(): array
    {
        $table_prefix = $this->config()->get('table_prefix');
        $collection = [];

        $tables = DB::query(sprintf('SHOW TABLES LIKE \'%s%%\'', Convert::raw2sql($table_prefix)))->column();

        if (empty($tables)) {
            return $collection;
        }

        foreach ($tables as $table) {
            if (in_array(substr($table, strlen($table_prefix)), $this->config()->get('skip_tables'))) {
                // If table name without prefix is in "skip_tables" config, then skip.
                continue;
            }

            // Add DROP TABLE statement to collection.
            $collection[] = new RawSQL(sprintf('DROP TABLE \'%s\'', Convert::raw2sql($table)));
        }

        return $collection;
    }
}
