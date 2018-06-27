<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyConfigEx.php';

use FC\Resque\Resque;

class MyResqueEx
{
    public static function enqueue($queue, $task, $args)
    {
        Resque::enqueue($queue, $task, $args, TRUE);
    }
}

Resque::setBackend(MyConfigEx::Resque_RedisEnd);

