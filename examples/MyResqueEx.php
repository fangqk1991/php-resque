<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.local/MyConfig.php';

class MyResqueEx
{
    public static function enqueue($queue, $task, $args)
    {
        Resque::enqueue($queue, $task, $args, true);
    }
}

Resque::setBackend(sprintf('%s:%s', MyConfig::Resque_Host, MyConfig::Resque_Port));

