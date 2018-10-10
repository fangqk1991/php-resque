<?php

namespace FC\Resque\Schedule;

require_once __DIR__ . '/../../vendor/autoload.php';

use FC\Resque\Core\Resque;
use FC\Resque\Job\IResqueTask;

class RuleTask implements IResqueTask
{
    public function perform($params)
    {
        $uid = $params['uid'];
        $queue = $params['queue'];

        $job = RuleJob::find($queue, $uid);

        if($job instanceof RuleJob)
        {
            $job->consume();
            Resque::enqueue($job->queue, $job->class, $job->args);
        }
    }
}