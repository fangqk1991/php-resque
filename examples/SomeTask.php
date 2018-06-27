<?php

namespace FC\Example;

require_once __DIR__ . '/../vendor/autoload.php';

use \FC\Resque\TaskBase;

class SomeTask extends TaskBase
{
    public function myTask($params)
    {
        echo sprintf("%s %s %s start..\n", date('Y-m-d H:i:s'), __CLASS__, __FUNCTION__);
        echo json_encode($params) . "\n";
        sleep(1);
        echo sprintf("%s %s %s end!\n", date('Y-m-d H:i:s'), __CLASS__, __FUNCTION__);
    }

    public function onException(\Exception $e)
    {
        parent::onException($e); // TODO: Change the autogenerated stub
    }
}