<?php

namespace FC\Resque\Schedule;

use FC\Resque\Core\Resque;

class ScheduleLeader
{
    const kPrefix = 'schedule:';

    private static $_instance;

    private static function getInstance()
    {
        if (is_null(self::$_instance))
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    private function __clone()
    {
        die('Clone is not allowed.' . E_USER_ERROR);
    }

    public static function run()
    {
        echo "Start.\n";
        Resque::redis()->psubscribe(array('__key*__:expired'), function ($redis, $pattern, $channel, $t_key) {
            if(strpos($t_key, self::kPrefix) === 0)
            {
                var_dump($t_key);
            }
        });
    }
}