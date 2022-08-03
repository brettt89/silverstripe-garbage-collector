<?php

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GarbageCollector\CollectorInterface;
use SilverStripe\GarbageCollector\GarbageCollectorService;

class GarbageCollectorServiceTask extends BuildTask
{
    private $gcService;

    /**
     * @var string
     */
    protected $title = "Garbage Collector Service Task";

    /**
     * @var string
     */
    protected $description = "Removes old version records from the database.";

    public function run($request)
    {
        $this->process();
    }

    /**
     * Process all registered Collectors
     */
    protected function process()
    {
        $this->gcService = GarbageCollectorService::inst();

        foreach ($this->gcService->getCollectors() as $collector) {
            $this->processCollector($collector);
        }
    }

    /**
     * @param CollectorInterface $collector Collector to process
     */
    protected function processCollector(CollectorInterface $collector)
    {
        $processors = $this->gcService->getProcessors($collector);
        $collections = $collector->getCollections();

        // If no processors are present, skip.
        if (empty($processors)) {
            echo sprintf('No processors registered with %s <br>', $collector->getName());
            return;
        }

        if (empty($collections)) {
            echo sprintf('No collections were found for %s <br>', $collector->getName());
            return;
        }

        // Process collections
        foreach ($collections as $collection) {
            $this->processCollection($collection, $processors);
        }
    }

    /**
     * Process a Collection using array of Processors (if matching)
     *
     * @param mixed $collection Collection data
     * @param array $processors Array of Processors
     */
    protected function processCollection($collection, array $processors)
    {
        if (empty($processors)) {
            echo 'No Processors provided for Collection <br>';
            return;
        }

        if (is_array($collection) || $collection instanceof \Traversable && !$collection instanceof DataObject) {
            // If traversable object is provided, loop through its items to process;
            foreach ($collection as $item) {
                $this->processCollection($item, $processors);
            }
        } else {
            // Otherwise loop through processors and execute.
            foreach ($processors as $instance => $processor) {
                if ($collection instanceof $instance) {
                    try {
                        // Use Injector to create processor and execute
                        $proc = Injector::inst()->create($processor, $collection);
                        $records = $proc->process();

                        echo sprintf('Processed %d records for %s using %s <br>', $records, get_class($collection), $proc->getName());
                    } catch (\Exception $e) {
                        // Log failures and continue;
                        // TODO: Stop re-processing of failed deletion records and expose it for audit.
                        echo sprintf('Unable to process records: "%s" <br>', $e->getMessage());
                    }

                    // Item processed, move on
                    return;
                }
            }

            // No processor was able to be found.
            echo sprintf('Unable to find processor for %s <br>', get_class($collection));
        }
    }
}
