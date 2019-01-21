<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FC\Resque\Core\Resque;
use FCResque\Demos\Tasks\TaskDemo;

// Redis 地址需要和 resque-demo.json 一致
Resque::setBackend('127.0.0.1:6379');

// 两种调用形式
Resque::enqueue('TaskQueueDemo', TaskDemo::class, ['msg' => 'Hello.']);
Resque::enqueue('TaskQueueDemo', '\FCResque\Demos\Tasks\TaskDemo', ['msg' => 'Hello again.']);

