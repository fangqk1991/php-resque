<?php

namespace FC\Resque\Schedule;

use FC\Resque\Core\Resque;

class ScheduleLeader
{
    const kPrefix = 'schedule:';

    public function watch()
    {
        $this->log('*** Starting ScheduleLeader ');
        Resque::redis()->psubscribe(array('__key*__:expired'), function ($redis, $pattern, $channel, $t_key) {
            if(strpos($t_key, self::kPrefix) === 0)
            {
                var_dump($t_key);
            }
        });
    }

    private function log($msg)
    {
        fwrite(STDOUT, sprintf('%s %s%s', date('Y-m-d H:i:s'), $msg, PHP_EOL));
    }
}