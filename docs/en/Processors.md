# Processors

Processors are used to process the items collected by a collector. They return an 'Implementor Class' string which is used to match against Datasets passed by a collection.

## DataList Processor

The `DataListProcessor` class is a basic processor which is used to delete a List of DataObjects from the Database.

**Implementor Class:** `SilverStripe\ORM\DataList`

## SQLExpression Processor

The `SQLExpressionProcessor` class is more advanced processor which is used execute SQLDelete queries against the Database. It will convert any `SQLExpression` within a collection into an `SQLDelete` statement and process is.

**Implementor Class:** `SilverStripe\ORM\Queries\SQLExpression`
