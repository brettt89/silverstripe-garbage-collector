<?php

namespace SilverStripe\GarbageCollector\Jobs;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\GarbageCollector\CollectorInterface;
use SilverStripe\GarbageCollector\GarbageCollectorService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * @property CollectorInterface|null $collector
 * @property int|null $batchSize
 * @property array $remaining
 * @property array $processors
 * @property array $versions
 * @property array $remainingVersions
 */
class RecurringAllGarbageCollectorJob extends AbstractQueuedJob
{
    use Configurable;
    /**
     * Constructor
     *
     * @var string
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Defines the title of the job
     * @return string
     */
    public function getTitle()
    {
        return sprintf("Garbage Collection processing for all collectors");
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->totalSteps = count($service ->getCollectors());
    }

    /**
     * @throws Exception
     */
    public function process(): void
    {
        $logger = new \Monolog\Handler\TestHandler();

        $service = GarbageCollectorService::inst();
        $service->setLogger(new \Monolog\Logger('TestLogger', [
            $logger
        ]));

        foreach ($service ->getCollectors() as $collector) {
            QueuedJobService::singleton()->queueJob(
                Injector::inst()->create(GarbageCollectorJob::class,$collector),
                DBDatetime::create()->setValue(time())->Rfc2822()
            );
            $this->currentStep += 1;
        }

        $this->queueNextJob();
        $this->isComplete = true;
    }

    /**
     * Check if there's already a queued or running job,
     * if not add one
     */
    public function requireDefaultJob(): void
    {
        $filter = [
            'Implementation' => RecurringAllGarbageCollectorJob::class,
            'JobStatus' => [
                QueuedJob::STATUS_NEW,
                QueuedJob::STATUS_INIT,
                QueuedJob::STATUS_RUN,
            ]
        ];
        if (QueuedJobDescriptor::get()->filter($filter)->count() > 0) {
            return;
        }
        $this->queueNextJob();
    }

    /**
     * Queue the next check for garbage collection. The default time frame is after 1 day.
     */
    private function queueNextJob(): void
    {
        $timestamp = time() + (self::config()->get('seconds_between_jobs') ?? 86400);
        QueuedJobService::singleton()->queueJob(
            Injector::inst()->create(self::class),
            DBDatetime::create()->setValue($timestamp)->Rfc2822()
        );
    }
}
