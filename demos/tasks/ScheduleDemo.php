<?php

namespace FCResque\Demos\Tasks;

use FC\Resque\Job\IResqueTask;

class ScheduleDemo implements IResqueTask
{
    public function perform($params)
    {
        $this->writeLog('Received Message: ' . $params['msg']);
    }

    private function writeLog($msg)
    {
        $fp = fopen(__DIR__ . '/../ScheduleDemo-Messages.txt', 'a');
        flock($fp, LOCK_EX) ;
        fwrite($fp, sprintf('%s Logging: %s%s', date('Y-m-d H:i:s'), $msg, PHP_EOL));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}