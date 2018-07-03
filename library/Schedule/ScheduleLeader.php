<?php

namespace FC\Resque\Schedule;

use Redis;

class ScheduleLeader
{
    private $redis;
    private static $_instance;

    private function __construct()
    {
        $this->redis = new Redis();
    }

    public static function getInstance()
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

    public function init($redisBackend)
    {
        list($host, $port) = explode(':', $redisBackend);
        $this->redis->connect($host, $port);
        $this->redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
    }

    public function run()
    {
        $this->writeLog('==== TimingTask START ====');
        $this->redis->psubscribe(array('__key*__:expired'), function ($redis, $pattern, $channel, $t_key) {
            $this->writeLog($t_key . ' | beginning');
        });
    }

    public function writeLog($message)
    {
        echo $message . "\n";
    }
}