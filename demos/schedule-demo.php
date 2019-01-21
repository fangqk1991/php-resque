<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FC\Resque\Core\Resque;
use FC\Resque\Schedule\LoopRule;
use FC\Resque\Schedule\RuleJob;
use FC\Resque\Schedule\ScheduleJob;
use FCResque\Demos\Tasks\ScheduleDemo;

// Redis 地址需要和 resque-demo.json 一致
Resque::setBackend('127.0.0.1:6379');

// 使用定时任务，需要修改 redis 相关配置并重启，将 resque-demo.json 中 useSchedule 字段设置为 true 并重启
// https://fqk.io/about-timing-task/

$uid = uniqid();
$msg = sprintf(sprintf('Job[%s] call performAfterDelay 5 at %s.', $uid, date('Y-m-d H:i:s')));
$job = ScheduleJob::create($uid, 'TaskQueueDemo', ScheduleDemo::class, ['msg' => $msg]);
$job->performAfterDelay(5);
echo $msg . PHP_EOL;

$uid = uniqid();
$msg = sprintf(sprintf('Job[%s] call performAtTimestamp time() + 10 at %s.', $uid, date('Y-m-d H:i:s')));
$job = ScheduleJob::create(uniqid(), 'TaskQueueDemo', ScheduleDemo::class, ['msg' => $msg]);
$job->performAtTimestamp(time() + 10);
echo $msg . PHP_EOL;

$uid = uniqid();
$exeTime = date('Y-m-d H:i:s', time() + 15);
$msg = sprintf(sprintf('Job[%s] call performAtTimeStr %s at %s.', $uid, $exeTime, date('Y-m-d H:i:s')));
$job = ScheduleJob::create($uid, 'TaskQueueDemo', ScheduleDemo::class, ['msg' => $msg]);
$job->performAtTimeStr($exeTime);
echo $msg . PHP_EOL;

// 循环任务
$ruleJob = RuleJob::create(uniqid(), 'TaskQueueDemo', ScheduleDemo::class, ['msg' => 'Rule job running.']);
$curTime = time();
$loopRule = LoopRule::generate($curTime, $curTime + 60, 1);
$ruleJob->performWithRule($loopRule);

echo 'Please see the log.' . PHP_EOL;


