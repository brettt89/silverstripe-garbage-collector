# Garbage Collector Service

Garbage Collector Service is used as a register for collectors to be processed. Registering collections can be done via [SilverStripe's Configuration API](https://docs.silverstripe.org/en/4/developer_guides/configuration/configuration/) as this class is Configurable.

```
SilverStripe\GarbageCollector\GarbageCollectorService:
  collectors:
    - MyCollector
    - MyOtherCollector
```
Registered collectors can be obtained statically from the service by using `GarbageCollectorService::inst()->getCollectors()`.

## Running Garbage Collection

Garbage Collection can be triggerred by calling `GarbageCollectorService::inst()->process()`. You may want to implement this into a recurring method such as QueuedJobs or BuildTasks for reoccuring execution.