<?php

namespace FC\Resque\Core;

use Exception;

class ResqueTrigger implements IResqueObserver
{
    private $_observers;

    public function __construct()
    {
        $this->_observers = array();
    }

    public function addObserver(IResqueObserver $observer)
    {
        array_push($this->_observers, $observer);
    }

    public function onWorkerStart(ResqueWorker $worker)
    {
        $this->log('*** Starting worker: ' . $worker->getID());

        foreach ($this->_observers as $observer)
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

        foreach ($this->_observers as $observer)
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

        foreach ($this->_observers as $observer)
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

        foreach ($this->_observers as $observer)
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

        foreach ($this->_observers as $observer)
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

        foreach ($this->_observers as $observer)
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