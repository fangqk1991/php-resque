<?php

namespace FC\Resque;

use Exception;

abstract class TaskBase implements IResqueTask
{
    public abstract function myTask($params);

    protected $queue;
    protected $args;

    public function init($queue, $args)
    {
        $this->queue = $queue;
        $this->args = $args;
    }

    public function perform()
    {
        $args = $this->args;

        try
        {
            $this->myTask($args);
        }
        catch (Exception $e)
        {
            $this->onException($e);
        }
    }

    public function onException(Exception $e)
    {
        echo sprintf("%s [Error: %d] %s \n", date('Y-m-d H:i:s'), $e->getCode(), $e->getMessage());
    }

    public static function create($className, $args, $queue)
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