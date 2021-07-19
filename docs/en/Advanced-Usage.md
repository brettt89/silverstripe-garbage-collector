# Advanced Usage

## Creating your own Collector

You can define your own custom Collectors to be used for Garbage Collection. The below example is a basic Collector example that uses the `DataListProcessor`.

```php
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

## Creating your own Processor

You can use the `AbstractProcessor` to help you build a custom Processor for SilverStripe Garbage Collection. Essentially it is just a class that can return an Implementor class to identify when it should be applied, and have a `process` method for executing against a provided collection item.

```php
class MyCollector extends AbstractCollector
{
    $this->object;
    
    public function __construct(MyCustomClass $object, string $name = '')
    {
        $this->object = $object;
        parent::__construct($name);
    }
    
    public function process(): int
    {
        // Perform your logic here
    }
    
    public function getImplementorClass(): string
    {
        return MyCustomClass::class;
    }
}
```
