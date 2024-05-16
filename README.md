# SilverStripe Garbage Collection Module

[![PHPUnit](https://github.com/brettt89/silverstripe-garbage-collector/actions/workflows/php.yml/badge.svg)](https://github.com/brettt89/silverstripe-garbage-collector/actions/workflows/php.yml) [![codecov](https://codecov.io/gh/brettt89/silverstripe-garbage-collector/branch/master/graph/badge.svg?token=FEEEJP8377)](https://codecov.io/gh/brettt89/silverstripe-garbage-collector)

## Overview

SilverStripe Module for defining and processing Garbage Collection on SilverStripe Applications.

## Installation

```
composer require brettt89/silverstripe-garbage-collector
```

## Basic Usage

 The below example shows how you can enable and configure the VersionedCollector and ChangeSetCollector for your application.

```yml
---
Name: GarbageCollectors
---
SilverStripe\GarbageCollector\Collectors\VersionedCollector:
  # Increase Versioned keep limit to 10 records
  keep_limit: 10
  # Define base classes to collect versions for
  base_classes:
    - SilverStripe\CMS\Model\SiteTree

SilverStripe\GarbageCollector\Collectors\ChangeSetCollector:
  # Reduce Changeset Lifetime to 10 days
  deletion_lifetime: 10

# Register collectors with service
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
    - 'SilverStripe\GarbageCollector\Collectors\VersionedCollector'
    - 'SilverStripe\GarbageCollector\Collectors\ChangeSetCollector'

#Queue a RecurringAllGarbageCollectorJob if there isn't one already. It will then re-queue itself to run once a day.
Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor:
  extensions:
    - 'SilverStripe\GarbageCollector\Extensions\QueuedJobDescriptorExtension'
```

The example setup will create a job that run all garbadge collectors every day after running dev build. It does this by calling `GarbageCollectorService::inst()->process();`. 

You also may decide to do this with some other process (BuildTask with Crontab that calls `GarbageCollectorService::inst()->process();`)

## Documentation

Garbage Collection is based on the idea of removal/processing of records and items that may not have native garbage collection. This should be extendable to work with any type of data set as long as there are corresponding processors.

### Components

 - [Collectors](./docs/en/Collectors.md)
 - [Processors](./docs/en/Processors.md)
 - [Garbage Collector Service](./docs/en/Garbage-Collector-Service.md)

### Guides

 - [Advanced Usage](./docs/en/Advanced-Usage.md)

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

- [silverstripe/framework](https://github.com/silverstripe/silverstripe-framework)
