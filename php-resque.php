#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Launch\Progress;
use FC\Resque\Launch\ResqueConfig;
use FC\Resque\Launch\ResqueLauncher;
use FC\Resque\Resque;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

$cmd = isset($argv[1]) ? $argv[1] : '';

$config = ResqueConfig::configFromFile(__DIR__ . '/config.local/config.json');

if($cmd === '--launch')
{
    foreach ($config->progresses as $progress)
    {
        if($progress instanceof Progress)
        {
            $pid = Resque::fork();

            if ($pid === 0) {

                Resque::setBackend($config->redisBackend);

                foreach ($progress->includes as $file)
                {
                    include_once $file;
                }

                $worker = new ResqueWorker($progress->queues, new ResqueTrigger());
                $worker->work();

                return ;
            }
        }
    }

    Resque::setBackend($config->redisBackend);
    $worker = new ResqueWorker(['resque-signal'], new ResqueTrigger());
    $worker->work();
}
else
{
    $starter = new ResqueLauncher($argv[0], $config);
    $starter->handle($cmd);
}




