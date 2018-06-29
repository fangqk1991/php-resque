<?php

namespace FC\Example;

require_once __DIR__ . '/../vendor/autoload.php';

use \FC\Resque\TaskBase;

class SomeTask extends TaskBase
{
    public function perform($params)
    {
        echo __CLASS__ . " start \n";

        $delay = intval($params['delay']);
        while ($delay--)
        {
            echo $delay . "\n";
            sleep(1);
        }

        echo __CLASS__ . " end \n";
    }
}