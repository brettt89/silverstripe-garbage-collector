<?php

namespace SilverStripe\GarbageCollector\Jobs;

use Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\Queries\SQLConditionalExpression;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\GarbageCollector\CollectorInterface;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * @property array $versions
 * @property array $remainingVersions
 */
class GarbageCollectorJob extends AbstractQueuedJob
{

    /**
     * Constructor
     *
     * @var string
     */
    public function __construct(CollectorInterface $collector, $batchSize = 10)
    {
        $this->collector = $collector;
        $this->batchSize = $batchSize;
        $processors = [];

        foreach ($collector->getProcessors() as $processor) {
            $processors[$processor::getImplementorClass()] = $processor;
        }
        
        $this->processors = $processors;
    }

    /**
     * Defines the title of the job
     * @return string
     */
    public function getTitle()
    {
        return sprintf("Garbage Collection processing for %s collector", $this->collector->getName());
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $collections = $this->collector->getCollections();
        $this->remaining = $collections;

        // Batch processing based on batchSize
        // ceil is used here to ensure an integer
        $this->totalSteps = ceil(count($collections) / $this->batchSize);
    }

    /**
     * @throws Exception
     */
    public function process(): void
    {
        $remaining = $this->remaining;

        // Loop over batched collections and process
        for ($i = 0; $i < $this->batchSize; $i++) {
            // check for trivial case
            if (count($remaining) === 0) {
                $this->isComplete = true;
                return;
            }

            if (count($this->processors) === 0) {
                throw new Exception(sprintf('No Processors found for collector %s', $this->collector->getName()));
            }

            $collection = array_shift($remaining);
            $this->processCollection($collection);

            // update job progress
            $this->remaining = $remaining;
        }

        $this->currentStep += 1;

        // check for job completion
        if (count($remaining) > 0) {
            return;
        }

        $this->isComplete = true;
    }

    protected function processCollection(array $collection)
    {
        foreach ($collection as $item) {
            if (is_array($item) || $item instanceof \Traversable && !$item instanceof DataObject) {
                // If traversable object is provided, loop through items to process;
                $this->processCollection($item);
            } else {
                // Otherwise loop through processors and execute.
                foreach ($this->processors as $instance => $processor) {
                    if ($item instanceof $instance) {
                        try {
                            // Use Injector to create processor and execute
                            $proc = Injector::inst()->create($processor, $item);
                            $records = $proc->process();

                            $this->addMessage(sprintf('Processed %d records for %s using %s', $records, get_class($item), $proc->getName()));
                        } catch (Exception $e) {
                            // Log failures and continue;
                            // TODO: Stop re-processing of failed deletion records and expose it for audit.
                            $this->addMessage(sprintf('Unable to process records: "%s"', $e->getMessage()));
                        }
                        
                        // Move on to next item
                        continue 2;
                    }
                }
                // No processor was able to be found.
                $this->addMessage(sprintf('Unable to find processor for %s', get_class($item)));
            }
        }
    }
}
