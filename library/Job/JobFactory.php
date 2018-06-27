<?php

namespace FC\Resque\Job;

use FC\Resque\ResqueException;
use FC\Resque\TaskBase;

class JobFactory implements IJobFactory
{
    public function create($className, $args, $queue)
    {
        if (!class_exists($className) || !(new $className instanceof TaskBase)) {
            throw new ResqueException(
                'Could not find job class ' . $className . '.'
            );
        }

        $task = new $className();
        if($task instanceof TaskBase)
        {
            $task->init($queue, $args);
        }
        return $task;
    }
}
