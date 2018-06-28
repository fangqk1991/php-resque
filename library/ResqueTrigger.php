<?php

namespace FC\Resque;

use Exception;

class ResqueTrigger implements IResqueTrigger
{
    public function onMasterStart(ResqueWorker $worker)
    {
        fwrite(STDOUT, '*** Starting worker ' . $worker->getId() . PHP_EOL);
    }

    public function onJobFound(ResqueJob $job)
    {
        fwrite(STDOUT, '*** find job ' . $job . PHP_EOL);
    }

    public function onJobPerform(ResqueJob $job)
    {
        // TODO: Implement onJobPerform() method.
    }

    public function onJobDone(ResqueJob $job)
    {
        // TODO: Implement onJobDone() method.
    }

    public function onJobFailed(ResqueJob $job, Exception $e)
    {
        // TODO: Implement onJobFailed() method.
    }

    public function onSalveCreated($pid)
    {
        // TODO: Implement onSalveCreated() method.
    }

    public function onSignalReceived($msg)
    {
        // TODO: Implement onSignalReceived() method.
    }
}