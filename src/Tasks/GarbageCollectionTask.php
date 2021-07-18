<?php

namespace SilverStripe\GarbageCollector\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\GarbageCollector\GarbageCollectorService;
use SilverStripe\GarbageCollector\Jobs\GarbageCollectorJob;

class GarbageCollectorTask extends BuildTask
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
        
        foreach (GarbageCollectorService::inst()->getCollectors() as $collector) {
            $job = new GarbageCollectorJob($collector);
            $service->queueJob($job);
        }
    }
}