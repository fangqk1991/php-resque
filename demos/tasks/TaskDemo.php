<?php

namespace FCResque\Demos\Tasks;

use FC\Resque\Job\IResqueTask;

class TaskDemo implements IResqueTask
{
    public function perform($params)
    {
        $msg = $params['msg'];

        $this->writeLog('Received Message: ' . $msg);

        $this->writeLog('Sleep 2s');
        sleep(2);
        $this->writeLog('Wake up');
    }

    private function writeLog($msg)
    {
        $fp = fopen(__DIR__ . '/../TaskDemo-Messages.txt', 'a');
        flock($fp, LOCK_EX) ;
        fwrite($fp, sprintf('%s Logging: %s%s', date('Y-m-d H:i:s'), $msg, PHP_EOL));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}