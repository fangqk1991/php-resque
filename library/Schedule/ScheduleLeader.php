<?php

namespace FC\Resque\Schedule;

use Redis;

class ScheduleLeader
{
    const kPrefix = 'schedule:';

    private $_redisHost;
    private $_redisPort;

    public function __construct($redisBackend)
    {
        list($host, $port) = explode(':', $redisBackend);
        $this->_redisHost = $host;
        $this->_redisPort = $port;
    }

    public function watch()
    {
        $this->log('*** Starting ScheduleLeader ');

        $this->consumeJobs();

        $redis = new Redis();
        $redis->connect($this->_redisHost, $this->_redisPort);
        $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);

        $redis->psubscribe(array('__key*__:expired'), function ($redis, $pattern, $channel, $flagKey) {
            if(strpos($flagKey, self::kPrefix) === 0)
            {
                $this->consumeJobs();
            }
        });
    }

    private function log($msg)
    {
        fwrite(STDOUT, sprintf('%s %s%s', date('Y-m-d H:i:s'), $msg, PHP_EOL));
    }

    private function consumeJobs()
    {
        $redis = new Redis();
        $redis->connect($this->_redisHost, $this->_redisPort);

        $items = $redis->zRangeByScore('schedule:jobs-zset', 0, time());
        foreach ($items as $jobKey)
        {
            $job = ScheduleJob::jobWithPayloadKey($jobKey);
            if($job instanceof ScheduleJob)
            {
                $job->run();
                $this->log($jobKey . ' execute..');
            }
        }
    }
}