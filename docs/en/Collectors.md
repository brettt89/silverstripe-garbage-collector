# Collectors

Collectors are used as a method of collecting data to be processed for deletion.

## Versioned Collector

The `VersionedCollector` class is used to get Version Database records that are considered expired* (see below) and provides an `SQLDelete` Statement that will remove all these records when executed. It has capability of batching queries for effeciency and can be configured via Configuration API.

> \* Expired is calculated using the `keep_lifetime` and `keep_limit` setting

### Configuration Options

| Parameter | Default | Type | Description |
|--|--|--|--|
| **keep_limit** | 2 | Integer | Number of Version records to always maintain |
| **keep_lifetime** | 180 | Integer | Age of records required for deletion |
| **deletion_record_limit** | 100 | Integer | Maximum number of base records for collection |
| **deletion_version_limit** | 100 | Integer | Maximum number of Versioned records per query |
| **query_limit** | 10 | Integer | Maximum number of SQL Queries to return in collection
| **base_classes** | Empty | Array* | Base classes to be collected against |
| **processors** | ** | Array* | Processor classes fot executing collection |

> \* Array of Strings

> \*\* Replacement for 'SQLExpressionProcessor::class' evaluation

### Fluent Extension

For Applications using Fluent, an Extension also exists for ensuring data from `_Localized_Versions` tables is also collected and deleted. Add the `FluentVersionCollectorExtension` class to the `VersionedCollector` in your Application.

```yml
---
Name: MyGarbageCollector
---
# Register Versioned collector with service
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
    - 'SilverStripe\GarbageCollector\Collectors\VersionedCollector'

# Apply Fluent extension to Versioned collector
SilverStripe\GarbageCollector\Collectors\VersionedCollector:
  extensions:
    - 'SilverStripe\GarbageCollector\Extensions\FluentVersionedCollectorExtension'
```

## ChangeSet Collector

The `ChangeSetCollector` class is used to get ChangeSet Database records that are considered expired* (see below) and provides an `SQLDelete` Statement that will remove all these records when executed. It has capability of batching queries for effeciency and can be configured via Configuration API.

> \* Expired is calculated using the `deletion_lifetime` setting

### Configuration Options

| Parameter | Default | Type | Description |
|--|--|--|--|
| **deletion_lifetime** | 100 | Integer | Age of records required for deletion |
| **deletion_limit** | 100 | Integer | Maximum number of ChangeSet records per query |
| **query_limit** | 5 | Integer | Maximum number of SQL Queries to return in collection
| **processors** | ** | Array* | Processor classes fot executing collection

> \* Array of Strings
> \** Replacement for 'SQLExpressionProcessor::class' evaluation

## ObsoleteTable Collector

The `ObsoleteTableCollector` class is used to get `_obsolete_` Database tables and provides a collection of `RawSQL` Statements that will remove all these tables when executed.
