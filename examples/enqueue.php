<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \FC\Resque\MyResque;

// 和 config.local/resque.main.config 中的配置对应

MyResque::init('127.0.0.1', 6488);
MyResque::enqueue('TASK_2', 'SomeTask2', array());
MyResque::enqueue('TASK_1', '\FC\Example\SomeTask', array());