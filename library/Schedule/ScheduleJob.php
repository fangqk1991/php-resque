<?php

namespace FC\Resque\Schedule;

use FC\Resque\Core\Resque;
use InvalidArgumentException;

class ScheduleJob
{
    private $_payload;

    private static function redis()
    {
        return Resque::redis();
    }

    public function __construct($payload)
    {
        $this->_payload = $payload;

        if (!isset($this->_payload['args'])) {
            $this->_payload['args'] = array();
        }

        if (!isset($this->_payload['id'])) {
            $this->_payload['id'] = md5(uniqid('', TRUE));
        }
    }

    public function getArguments()
    {
        return $this->_payload['args'];
    }

    public function getJobID()
    {
        return $this->_payload['id'];
    }

    public function getClassName()
    {
        return $this->_payload['class'];
    }

    public function getQueue()
    {
        return $this->_payload['queue'];
    }

    private function redisKey_jobFlag()
    {
        return sprintf('schedule:queue:%s:job:%s', $this->getQueue(), $this->getJobID());
    }

    private function redisKey_jobPayload()
    {
        return sprintf('schedule:queue:%s:job:%s:payload', $this->getQueue(), $this->getJobID());
    }

    private function redisKey_jobsSetForQueue()
    {
        return sprintf('schedule:queue:%s:jobs-zset', $this->getQueue());
    }

    private function redisKey_jobsSet()
    {
        return 'schedule:jobs-zset';
    }

    public function performAfterDelay($seconds)
    {
        return $this->performAtTime(time() + $seconds);
    }

    public function performAtTime($timestamp)
    {
        $timestamp = intval($timestamp);
        $seconds = $timestamp - time();

        if($seconds <= 0)
        {
            return FALSE;
        }

        $redis = self::redis();

        $flagKey = $this->redisKey_jobFlag();
        $jobKey = $this->redisKey_jobPayload();

        $redis->set($flagKey, 1);
        $redis->expire($flagKey, $seconds);

        $redis->set($jobKey, json_encode($this->_payload));
        $redis->zAdd($this->redisKey_jobsSetForQueue(), $timestamp, $jobKey);
        $redis->zAdd($this->redisKey_jobsSet(), $timestamp, $jobKey);

        return TRUE;
    }

    public function cancel()
    {
        $this->removeJob();
    }

    public function run()
    {
        Resque::enqueue($this->getQueue(), $this->getClassName(), $this->getArguments());
        $this->removeJob();
    }

    private function removeJob()
    {
        $redis = self::redis();
        $redis->del($this->redisKey_jobFlag());
        $redis->del($this->redisKey_jobPayload());
        $redis->del($this->redisKey_jobsSetForQueue());
        $redis->del($this->redisKey_jobsSet());
    }

    public static function find($queue, $uid)
    {
        $obj = new self(array(
            'id' => $uid,
            'queue' => $queue
        ));

        return self::jobWithPayloadKey($obj->redisKey_jobPayload());
    }

    public static function jobWithPayloadKey($jobKey)
    {
        $redis = self::redis();

        $payload = json_decode($redis->get($jobKey), TRUE);
        if(is_array($payload))
        {
            return new self($payload);
        }

        return NULL;
    }

    public static function create($uid, $queue, $class, array $args, $override = FALSE)
    {
        if($override)
        {
            if(self::find($queue, $uid) instanceof self)
            {
                return FALSE;
            }
        }

        if(!is_array($args)) {
            throw new InvalidArgumentException(
                'Supplied $args must be an array.'
            );
        }

        $payload = array(
            'queue' => $queue,
            'class' => $class,
            'args'  => $args,
            'id'    => empty($uid) ? md5(uniqid('', TRUE)) : $uid,
            'queue_time' => microtime(true),
        );

        return new ScheduleJob($payload);
    }
}