<?php

namespace FC\Resque\Schedule;

use FC\Model\FCModel;
use FC\Resque\Core\Resque;
use FC\Resque\Schedule\RuleTask;
use InvalidArgumentException;

class RuleJob extends FCModel
{
    public $uid;
    public $queue;
    public $class;
    public $args;
    public $createTime;
    public $version;

    /**
     * @var LoopRule
     */
    public $loopRule;


    private static function redis()
    {
        return Resque::redis();
    }

    protected function fc_defaultInit()
    {
        $this->version = 0;
        $this->createTime = microtime(true);
    }

    private function redisKey_jobPayload()
    {
        return sprintf('rule:queue:%s:job:%s:payload', $this->queue, $this->uid);
    }

    private function redisKey_jobsSetForQueue()
    {
        return sprintf('rule:queue:%s:jobs-zset', $this->queue);
    }

    private function redisKey_jobsSet()
    {
        return 'rule:jobs-zset';
    }

    public function performWithRule(LoopRule $loopRule)
    {
        $this->version += 1;
        $this->loopRule = $loopRule;
        $this->consume();
    }

    public function save()
    {
        $redis = self::redis();
        $jobKey = $this->redisKey_jobPayload();

        $redis->set($jobKey, json_encode($this->fc_encode()));
        $redis->zAdd($this->redisKey_jobsSetForQueue(), $this->createTime, $jobKey);
        $redis->zAdd($this->redisKey_jobsSet(), $this->createTime, $jobKey);
    }

    public function consume()
    {
        $nextTime = $this->loopRule->next();
        $this->save();

        if($nextTime)
        {
            $args = [
                'uid' => $this->uid,
                'queue' => $this->queue,
                'version' => $this->version,
            ];

            $scheduleJob = ScheduleJob::create(uniqid(), $this->queue, RuleTask::class, $args);

            if(!$scheduleJob->performAtTimestamp($nextTime))
            {
                Resque::enqueue($this->queue, RuleTask::class, $args);
            }
        }
    }

    public function cancel()
    {
        $redis = self::redis();
        $redis->del($this->redisKey_jobPayload());
        $redis->del($this->redisKey_jobsSetForQueue());
        $redis->del($this->redisKey_jobsSet());
    }

    public static function find($queue, $uid)
    {
        $obj = new self();
        $obj->uid = $uid;
        $obj->queue = $queue;

        $redis = self::redis();
        $jobKey = $obj->redisKey_jobPayload();

        $payload = json_decode($redis->get($jobKey), TRUE);
        if(is_array($payload))
        {
            $ruleJob = new self();
            $ruleJob->fc_generate($payload);

            return $ruleJob;
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

        $ruleJob = new self();
        $ruleJob->fc_generate([
            'uid' => empty($uid) ? md5(uniqid('', TRUE)) : $uid,
            'queue' => $queue,
            'class' => $class,
            'args' => $args,
        ]);

        return $ruleJob;
    }

    protected function fc_propertyMapper()
    {
        return [
            'uid' => 'uid',
            'queue' => 'queue',
            'class' => 'class',
            'args' => 'args',
            'loopRule' => 'loop_rule',
            'createTime' => 'create_time',
            'version' => 'version',
        ];
    }

    protected function fc_propertyClassMapper()
    {
        return [
            'loopRule' => '\FC\Resque\Schedule\LoopRule'
        ];
    }
}