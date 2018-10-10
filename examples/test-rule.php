<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyResqueEx.php';

use FC\Resque\Schedule\LoopRule;

$rule = new LoopRule(strtotime('today'), strtotime('+10 day'), 3600 * 24);
$conditions = [];

while($curTime = $rule->next())
{
    echo date('Y-m-d H:i:s', $curTime) . "\n";
}



