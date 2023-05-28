<?php

namespace SilverStripe\GarbageCollector\Jobs;

use Exception;
use SilverStripe\GarbageCollector\CollectorInterface;
use SilverStripe\GarbageCollector\GarbageCollectorService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

if (!class_exists(AbstractQueuedJob::class)) {
    return;
}

/**
 * @property CollectorInterface|null $collector
 * @property int|null $batchSize
 * @property array $remaining
 * @property array $processors
 * @property array $versions
 * @property array $remainingVersions
 */
class GarbageCollectorJob extends AbstractQueuedJob
{
    /**
     * @var GarbageCollectorService
     */
    private $service;

    /**
     * @var \Monolog\Handler\HandlerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @var string
     */
    public function __construct(?CollectorInterface $collector = null, $batchSize = 10)
    {
        parent::__construct();

        $this->collector = $collector;
        $this->batchSize = $batchSize;

        $this->logger = new \Monolog\Handler\TestHandler();

        $this->service = GarbageCollectorService::inst();
        $this->service->setLogger(new \Monolog\Logger('TestLogger', [
            $this->logger
        ]));
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
        $this->processors = $this->service->getProcessors($this->collector);

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

        // check for trivial case
        if (count($remaining) === 0) {
            $this->isComplete = true;
            return;
        }

        if (count($this->processors) === 0) {
            throw new Exception(sprintf('No Processors found for collector %s', $this->collector->getName()));
        }

        // Loop over batched collections and process
        for ($i = 0; $i < $this->batchSize; $i++) {
            // If no more processing, break out of loop.
            if (count($remaining) === 0) {
                break;
            }

            $collection = array_shift($remaining);
            $this->service->processCollection($collection, $this->processors);

            // update job progress
            $this->remaining = $remaining;
        }

        // Get messages from logs and add to Job
        foreach ($this->logger->getRecords() as $record) {
            $this->addMessage($record['message'], $record['level_name']);
        }

        $this->currentStep += 1;

        // check for job completion
        if (count($remaining) > 0) {
            return;
        }
        
        $this->isComplete = true;
    }
}
