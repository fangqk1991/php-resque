<?php

namespace FC\Resque\Core;

use Exception;

interface IResqueObserver
{
    public function onWorkerStart(ResqueWorker $worker);
    public function onJobFound(ResqueJob $job);
    public function onJobPerform(ResqueJob $job);
    public function onJobDone(ResqueJob $job);
    public function onJobFailed(ResqueJob $job, Exception $e);
    public function onSalveCreated($pid);
}
