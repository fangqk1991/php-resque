<?php

namespace FC\Resque\Schedule;

use FC\Resque\Core\Resque;
use FC\Resque\Job\IResqueTask;

class RuleTask implements IResqueTask
{
    public function perform($params)
    {
        $uid = $params['uid'];
        $queue = $params['queue'];
        $version = $params['version'];

        $job = RuleJob::find($queue, $uid);

        if($job instanceof RuleJob && $job->version === $version)
        {
            Resque::enqueue($job->queue, $job->class, $job->args);
            $job->consume();
        }
    }
}