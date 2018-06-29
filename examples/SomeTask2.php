<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \FC\Resque\TaskBase;

class SomeTask2 extends TaskBase
{
    public function perform($params)
    {
        echo sprintf("%s %s %s\n", date('Y-m-d H:i:s'), __CLASS__, __FUNCTION__);
    }
}