<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FC\Resque\Job\IResqueTask;

class SomeTask2 implements IResqueTask
{
    public function perform($params)
    {
        echo sprintf("%s %s %s\n", date('Y-m-d H:i:s'), __CLASS__, __FUNCTION__);
    }
}