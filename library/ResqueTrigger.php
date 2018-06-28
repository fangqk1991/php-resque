<?php

namespace FC\Resque;

use Exception;

class ResqueTrigger implements IResqueTrigger
{
    public function onMasterStart(ResqueWorker $worker)
    {
        $this->log('*** Starting worker: ' . $worker->getId());
    }

    public function onJobFound(ResqueJob $job)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription());
    }

    public function onJobPerform(ResqueJob $job)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription());
    }

    public function onJobDone(ResqueJob $job)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription());
    }

    public function onJobFailed(ResqueJob $job, Exception $e)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription() . ' ' . $e->getMessage());
    }

    public function onSalveCreated($pid)
    {
        $this->log(__FUNCTION__ . ': ' . $pid);
    }

    public function onSignalReceived($msg)
    {
        $this->log(__FUNCTION__ . ': ' . $msg);
    }

    public function log($msg)
    {
        fwrite(STDOUT, sprintf('%s %s%s', date('Y-m-d H:i:s'), $msg, PHP_EOL));
    }
}