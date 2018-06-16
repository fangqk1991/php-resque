<?php

namespace FC\Resque;

use Exception;

abstract class ResqueTaskBase
{
    public abstract function myTask($params);

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
}