<?php

namespace SilverStripe\GarbageCollector\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\GarbageCollector\Jobs\RecurringAllGarbageCollectorJob;

class QueuedJobDescriptorExtension extends Extension
{
    /**
     * Called on dev/build by DatabaseAdmin
     */
    public function onAfterBuild(): void
    {
        RecurringAllGarbageCollectorJob::singleton()->requireDefaultJob();
    }
}
