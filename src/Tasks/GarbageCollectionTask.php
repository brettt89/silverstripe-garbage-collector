<?php

namespace Silverstripe\GarbageCollection\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Core\ClassInfo;
use Silverstripe\GarbageCollection\GarbageCollectionService;
use Silverstripe\GarbageCollection\Jobs\GarbageCollectionJob;

class GarbageCollectionTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'garbage-collection-task';

    /**
     * @var string
     */
    protected $title = 'Garbage Collection Task';

    /**
     * @var string
     */
    protected $description = 'Create Garbage Collection jobs for deleting records';

    /**
     * @param HTTPRequest $request
     * @throws ValidationException
     */
    public function run($request) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $service = QueuedJobService::singleton();
        
        foreach (GarbageCollectionService::getCollectors() as $collectorClass) {
            $job = new GarbageCollectionJob(new $collectorClass());
            $service->queueJob($job);
        }
    }
}