<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyResqueEx.php';

use FC\Resque\Schedule\LoopRule;
use FC\Resque\Schedule\RuleJob;

$curTime = time();
$loopRule = LoopRule::generate($curTime, $curTime + 20, 5);

//while ($cur = $loopRule->next())
//{
//    echo date('Y-m-d H:i:s', $cur) . "\n";
//}

MyResqueEx::setBackend(MyConfigEx::Resque_RedisEnd);

$ruleJob = RuleJob::create(uniqid(), 'TASK_1', 'TellTime', array('enqueue_time' => date('Y-m-d H:i:s', $curTime)));
$ruleJob->performWithRule($loopRule);

