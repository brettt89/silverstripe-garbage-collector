
# SilverStripe Garbage Collection Module

[![PHPUnit](https://github.com/brettt89/silverstripe-garbage-collector/actions/workflows/php.yml/badge.svg)](https://github.com/brettt89/silverstripe-garbage-collector/actions/workflows/php.yml)

:warning: :warning: **Warning: *In Development - Not Production Ready!*** :warning: :warning:

## Overview

Method for processing Garbage Collection on Database Records. It is based on an SQL statement being provided through interfaced methods that can be executed to cleanup records.

## Installation

```
composer require brettt89/silverstripe-garbage-collection
```

## How to use

Garbage Collector uses a combination of "Collectors" and "Processors" to perform garbage collection for a SilverStripe application.

### Basis Usage

In order to use the Garbage Collector, you will need to define your own Collector. Below is a basic example of a collector.

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

The above example `MyCollector` return a `DataList` of `LogRecords`, that have a 'Created Date' older than 10 days, and process them using the `DataListProcessor`;

#### Registering your Collectors

The `GarbageCollectionService` is used to keep a register of Collectors that are processed during Garbage Collection execution. You can use YML to define Collectors for your application.

```
---
Name: GarbageCollectors
---
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
	- 'MyCollector'
```

### Running Garbage Collection

The execution of Garbage Collection uses the [Silverstripe QueuedJobs](https://github.com/symbiote/silverstripe-queuedjobs/) module to run all registered collectors with the `GarbageCollectionService`.

Build task `GarbageCollectionTask` is available for executing collectors registered with the `GarbageCollectorService` on your environment.

```
./vendor/bin/sake dev/tasks/GarbageCollectionTask
```
Alternatively you can write your own implementation and use `GarbageCollectorService::inst()->getCollectors()` to obtain registered collectors for processing.

### Components

Garbage Collection is based on the idea of removal/processing of records and items that may not have native garbage collection. This should be extendable to work with any type of data set as long as there are corresponding processors.

#### Collectors

See: [AbstractCollector.php](./src/Collectors/AbstractCollector.php)

Collectors are used as a method of collecting data to be processed for deletion. The type of collection used here is matched up to Processors for performing garbage collection of those items.

#### Processors

See: [AbstractProcessor.php](./src/Processors/AbstractProcessor.php)

Processors are used to process the items collected by a collector for removal. There are some basic Processors included in this module that can be used, however you can also create your own Processors for custom data sets.

The method `getImplementorClass()` is used to identify which data set in the collection this Processor applies to.

#### Garbage Collection Service

See: [GarbageCollectorService.php](./src/GarbageCollectorService.php)

Garbage Collection Service is used as a register for collectors to be processed. Registering collections can be done via [SilverStripe's Configuration API](https://docs.silverstripe.org/en/4/developer_guides/configuration/configuration/) as this class is Configurable.

```
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
	- MyCollector
	- MyOtherCollector
```
Registered collectors can be obtained statically from the service by using `GarbageCollectorService::inst()->getCollectors()`.