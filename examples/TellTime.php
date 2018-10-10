<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FC\Resque\Job\IResqueTask;

class TellTime implements IResqueTask
{
    public function perform($params)
    {
        $fp = fopen(__DIR__ . '/../run.local/tell-time.txt', "a");
        flock($fp, LOCK_EX) ;
        fwrite($fp, sprintf('%s %s%s', date('Y-m-d H:i:s'), $params['enqueue_time'], PHP_EOL));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}