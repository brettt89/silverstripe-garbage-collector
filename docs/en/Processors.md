# Processors

Processors are used to process the items collected by a collector. They return an 'Implementor Class' string which is used to match against Datasets passed by a collection.

## DataList Processor

The `DataListProcessor` class is a basic processor which is used to delete a List of DataObjects from the Database.

**Implementor Class:** `SilverStripe\ORM\DataList`

## SQLExpression Processor

The `SQLExpressionProcessor` class is more advanced processor which is used execute SQLDelete queries against the Database. It will convert any `SQLExpression` within a collection into an `SQLDelete` statement and process is.

**Implementor Class:** `SilverStripe\ORM\Queries\SQLExpression`

## RawSQL Processor

The `RawSQLProcessor` class is a basic processor which is used to execute raw SQL directly on the SilverStripe Application's Database. A Custom Model is provided so that Implementor Class matching can occur on the provided query.

**Implementor Class:** `SilverStripe\GarbageCollector\Models\RawSQL`
