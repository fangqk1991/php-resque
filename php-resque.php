#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Launch\ResqueConfig;
use FC\Resque\Launch\ResqueLauncher;
use FC\Resque\Resque;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

$cmd = isset($argv[1]) ? $argv[1] : '';

$config = ResqueConfig::configFromFile(__DIR__ . '/config.local/config.json');

if($cmd === '--launch')
{
    Resque::setBackend($config->redisBackend);

    foreach ($config->includes as $file)
    {
        require_once $file;
    }

    $worker = new ResqueWorker($config->queues, new ResqueTrigger());
    $worker->work();
}
else
{
    $starter = new ResqueLauncher($argv[0], $config);
    $starter->handle($cmd);
}




