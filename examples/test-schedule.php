<?php

require_once __DIR__ . '/../vendor/autoload.php';
//
use FC\Resque\Schedule\ScheduleLeader;

ScheduleLeader::getInstance()->init('127.0.0.1:6488');
ScheduleLeader::getInstance()->run();