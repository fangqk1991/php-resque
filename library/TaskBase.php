<?php

namespace FC\Resque;

abstract class TaskBase implements IResqueTask
{
    public abstract function perform($params);

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