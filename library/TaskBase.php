<?php

namespace FC\Resque;

use Exception;

abstract class TaskBase implements IResqueTask
{
    public abstract function myTask($params);

    public function perform($args)
    {
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

    public static function create($className)
    {
        if (!class_exists($className) || !(new $className instanceof TaskBase)) {
            throw new ResqueException(
                'Could not find job class ' . $className . '.'
            );
        }

        return new $className();
    }
}