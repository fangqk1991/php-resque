<?php

namespace FC\Resque\Core;

use Exception;

class ResqueTrigger
{
    private function observers()
    {
        return Resque::observers();
    }

    public function onWorkerStart(ResqueWorker $worker)
    {
        $this->log('*** Starting worker: ' . $worker->getID());

        foreach ($this->observers() as $observer)
        {
            if($observer instanceof IResqueObserver)
            {
                $observer->onWorkerStart($worker);
            }
        }
    }

    public function onJobFound(ResqueJob $job)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription());

        foreach ($this->observers() as $observer)
        {
            if($observer instanceof IResqueObserver)
            {
                $observer->onJobFound($job);
            }
        }
    }

    public function onJobPerform(ResqueJob $job)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription());

        foreach ($this->observers() as $observer)
        {
            if($observer instanceof IResqueObserver)
            {
                $observer->onJobPerform($job);
            }
        }
    }

    public function onJobDone(ResqueJob $job)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription());

        foreach ($this->observers() as $observer)
        {
            if($observer instanceof IResqueObserver)
            {
                $observer->onJobDone($job);
            }
        }
    }

    public function onJobFailed(ResqueJob $job, Exception $e)
    {
        $this->log(__FUNCTION__ . ': ' . $job->getDescription() . ' ' . $e->getMessage());

        foreach ($this->observers() as $observer)
        {
            if($observer instanceof IResqueObserver)
            {
                $observer->onJobFailed($job, $e);
            }
        }
    }

    public function onSalveCreated($pid)
    {
        $this->log(__FUNCTION__ . ': ' . $pid);

        foreach ($this->observers() as $observer)
        {
            if($observer instanceof IResqueObserver)
            {
                $observer->onSalveCreated($pid);
            }
        }
    }

    private function log($msg)
    {
        fwrite(STDOUT, sprintf('%s %s%s', date('Y-m-d H:i:s'), $msg, PHP_EOL));
    }
}