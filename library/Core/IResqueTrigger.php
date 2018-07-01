<?php

namespace FC\Resque\Core;

use Exception;
use FC\Resque\ResqueJob;
use FC\Resque\ResqueWorker;

interface IResqueTrigger
{
    public function onWorkerStart(ResqueWorker $worker);
    public function onJobFound(ResqueJob $job);
    public function onJobPerform(ResqueJob $job);
    public function onJobDone(ResqueJob $job);
    public function onJobFailed(ResqueJob $job, Exception $e);
    public function onSalveCreated($pid);

    public function onSignalReceived($msg);
}
