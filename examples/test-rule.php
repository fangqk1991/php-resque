<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyResqueEx.php';

use FC\Resque\Schedule\LoopRule;
use FC\Resque\Schedule\RuleJob;

$loopRule = LoopRule::generate(time(), time() + 100, 5);

//while ($cur = $loopRule->next())
//{
//    echo date('Y-m-d H:i:s', $cur) . "\n";
//}

MyResqueEx::setBackend(MyConfigEx::Resque_RedisEnd);

$ruleJob = RuleJob::create(uniqid(), 'TASK_1', 'SomeTask2', array('arg-2' => 'arg-2'));
$ruleJob->performWithRule($loopRule);

