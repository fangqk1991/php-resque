<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyConfigEx.php';

class MyResqueEx
{
    public static function enqueue($queue, $task, $args)
    {
        Resque::enqueue($queue, $task, $args, true);
    }
}

Resque::setBackend(MyConfigEx::Resque_RedisEnd);

