<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyResqueEx.php';

use FC\Resque\Schedule\LoopRule;
use FC\Resque\Schedule\RuleJob;

MyResqueEx::setBackend(MyConfigEx::Resque_RedisEnd);

$curTime = time();
$ruleJob = RuleJob::create(uniqid(), 'TASK_1', 'TellTime', array('xxx' => 'test' . rand(0, 10000)));

$loopRule = LoopRule::generate($curTime, $curTime + 3, 1);
$ruleJob->performWithRule($loopRule);

$curTime = time();
$loopRule = LoopRule::generate($curTime, $curTime + 5, 1);
$ruleJob->performWithRule($loopRule);

