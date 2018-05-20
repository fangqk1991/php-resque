<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyResqueEx.php';

MyResqueEx::enqueue('TASK_2', 'SomeTask2', array());
MyResqueEx::enqueue('TASK_1', '\FC\Example\SomeTask', array());