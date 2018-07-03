<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FC\Resque\Core\Resque;
use FC\Resque\Launch\FCMaster;
use FC\Resque\Schedule\ScheduleLeader;

$master = FCMaster::masterFromFile(__DIR__ . '/../config.local/resque.json');
Resque::setBackend($master->redisBackend);

$leader = new ScheduleLeader($master->redisBackend);
$leader->watch();
