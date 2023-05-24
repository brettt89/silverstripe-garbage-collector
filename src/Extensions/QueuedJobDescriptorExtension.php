<?php

namespace SilverStripe\GarbageCollector\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\GarbageCollector\Jobs\AllGarbageCollectorJob;

class QueuedJobDescriptorExtension extends Extension
{
    /**
     * Called on dev/build by DatabaseAdmin
     */
    public function onAfterBuild(): void
    {
        AllGarbageCollectorJob::singleton()->requireDefaultJob();
    }
}
