<?php

namespace FC\Resque\Schedule;

use Redis;

class ScheduleLeader
{
    const kPrefix = 'schedule:';

    private $_redisBackend;

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

    public static function setBackend($server)
    {
        $instance = self::getInstance();
        $instance->_redisBackend = $server;
    }

    public static function run()
    {
        list($host, $port) = explode(':', self::getInstance()->_redisBackend);

        $redis = new Redis();
        $redis->connect($host, $port);
        $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);

        echo "Start.\n";
        $redis->psubscribe(array('__key*__:expired'), function ($redis, $pattern, $channel, $t_key) {
            if(strpos($t_key, self::kPrefix) === 0)
            {
                var_dump($t_key);
            }
        });
    }
}