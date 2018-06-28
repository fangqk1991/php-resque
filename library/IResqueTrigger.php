<?php

namespace FC\Resque;

use Exception;

interface IResqueTrigger
{
    public function onMasterStart(ResqueWorker $worker);
    public function onJobFound(ResqueJob $job);
    public function onJobPerform(ResqueJob $job);
    public function onJobDone(ResqueJob $job);
    public function onJobFailed(ResqueJob $job, Exception $e);
    public function onSalveCreated($pid);

    public function onSignalReceived($msg);
}
