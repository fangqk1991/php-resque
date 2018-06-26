<?php

namespace FC\Example;

require_once __DIR__ . '/../vendor/autoload.php';

use \FC\Resque\ResqueTaskBase;

class SomeTask extends ResqueTaskBase
{
    public function myTask($params)
    {
        echo sprintf("%s %s %s\n", date('Y-m-d H:i:s'), __CLASS__, __FUNCTION__);
    }

    public function onException(\Exception $e)
    {
        parent::onException($e); // TODO: Change the autogenerated stub
    }
}