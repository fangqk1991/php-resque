<?php

namespace FC\Resque;

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
        catch (\Exception $e)
        {
            $this->onException($e);
        }
    }

    public function onException(\Exception $e)
    {
        echo "Error: " . date('Y-m-d H:i:s') . "\n";
    }
}