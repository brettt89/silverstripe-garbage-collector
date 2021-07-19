# SilverStripe Garbage Collection Module

[![PHPUnit](https://github.com/brettt89/silverstripe-garbage-collector/actions/workflows/php.yml/badge.svg)](https://github.com/brettt89/silverstripe-garbage-collector/actions/workflows/php.yml) [![codecov](https://codecov.io/gh/brettt89/silverstripe-garbage-collector/branch/master/graph/badge.svg?token=FEEEJP8377)](https://codecov.io/gh/brettt89/silverstripe-garbage-collector)


:warning: :warning: **Warning: *In Development - Not Production Ready!*** :warning: :warning:

## Overview

Method for processing Garbage Collection on Database Records. It is based on an SQL statement being provided through interfaced methods that can be executed to cleanup records.

## Installation

```
composer require brettt89/silverstripe-garbage-collection
```

## How to use

Garbage Collector uses a combination of "Collectors" and "Processors" to perform garbage collection for a SilverStripe application.

### Basic Usage

Some default collectors are available for usage immediately with this module. The below example shows how you can enable and configure the VersionedCollector and ChangeSetCollector for your application.

```yml
---
Name: GarbageCollectors
---
SilverStripe\GarbageCollector\Collectors\VersionedCollector:
  # Increase Versioned keep limit to 10 records
  keep_limit: 10

SilverStripe\GarbageCollector\Collectors\ChangeSetCollector:
  # Reduce Changeset Lifetime to 10 days
  deletion_lifetime: 10

# Register collectors with service
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
    - 'SilverStripe\GarbageCollector\Collectors\VersionedCollector'
    - 'SilverStripe\GarbageCollector\Collectors\ChangeSetCollector'
```

Now we just need to define an execution for the GarbageCollectorService by calling `GarbageCollectorService::inst()->process();`. You may decide to do this in a BuildTask or Job depending on how you want to execute Garbage Collection (e.g. Crontab).

### Advanced Usage

You can define your own custom Collectors to be used for Garbage Collection. The below example is a basic Collector example that uses the `DataListProcessor`.

```
use SilverStripe\GarbageCollector\Collectors\AbstractCollector;
use SilverStripe\GarbageCollector\Processors\DataListProcessor;
use SilverStripe\ORM\FieldType\DBDatetime;

class MyCollector extends AbstractCollector
{
    private  static  $processors = [
        DataListProcessor::class
    ];
    
    public function getName(): string
    {
        return 'MyCustomCollector';
    }

    public  function  getCollections(): array
    {
        $collection = [];

        // Filter grabs MyObject records older than 10 days
        $dateFilter = DBDatetime::create_field('Datetime', DBDatetime::now()->Rfc2822());
                ->modify('- 10 days')
                ->Rfc2822();
        
        // Add Datalist to Collection
        $collection[] = LogRecords::get()->filter([
            'DateCreated:LessThanOrEqual' => $dateFilter
        ]);

        return $collection;
    }
{
```

The above example `MyCollector` return a `DataList` of `LogRecords`, that have a 'Created Date' older than 10 days, and process them using the `DataListProcessor`. Now all that we need to do is register our Collector with the `GarbageCollectorService`.

```
---
Name: MyGarbageCollectors
---
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
    - 'MyCollector'
```

### Running Garbage Collection

Garbage Collection can be triggerred by calling `GarbageCollectorService::inst()->process();`. You may want to implement this into a recurring method such as QueuedJobs or BuildTasks for reoccuring execution.

## Components

Garbage Collection is based on the idea of removal/processing of records and items that may not have native garbage collection. This should be extendable to work with any type of data set as long as there are corresponding processors.

#### Collectors

See: [AbstractCollector.php](./src/Collectors/AbstractCollector.php)

Collectors are used as a method of collecting data to be processed for deletion. The type of collection used here is matched up to Processors for performing garbage collection of those items.

#### Processors

See: [AbstractProcessor.php](./src/Processors/AbstractProcessor.php)

Processors are used to process the items collected by a collector for removal. There are some basic Processors included in this module that can be used, however you can also create your own Processors for custom data sets.

The method `getImplementorClass()` is used to identify which data set in the collection this Processor applies to.

#### Garbage Collector Service

See: [GarbageCollectorService.php](./src/GarbageCollectorService.php)

Garbage Collector Service is used as a register for collectors to be processed. Registering collections can be done via [SilverStripe's Configuration API](https://docs.silverstripe.org/en/4/developer_guides/configuration/configuration/) as this class is Configurable.

```
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
    - MyCollector
    - MyOtherCollector
```
Registered collectors can be obtained statically from the service by using `GarbageCollectorService::inst()->getCollectors()`.

## Reporting Issues

Please [create an issue](https://github.com/brettt89/silverstripe-garbage-collector/issues) for any bugs you've found, or features you're missing.

## License

This module is released under the [MIT License](LICENSE)

## Credits

This project is made possible by the community surrounding it and especially the wonderful people and projects listed in this document.

### Contributors

- Brett Tasker (https://github.com/brettt89)
- Mojmir Fendek (https://github.com/mfendeksilverstripe)

### Libraries

#### [silverstripe/framework] (https://github.com/silverstripe/silverstripe-framework)