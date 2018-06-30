#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Launch\ProgressWorker;
use FC\Resque\Launch\ProgressMaster;
use FC\Resque\Launch\ResqueLauncher;
use FC\Resque\Resque;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

$cmd = isset($argv[1]) ? $argv[1] : '';

$master = ProgressMaster::configFromFile(__DIR__ . '/config.local/config.json');

if($cmd === '--launch')
{
    foreach ($master->progresses as $progress)
    {
        if($progress instanceof ProgressWorker)
        {
            $pid = Resque::fork();

            if ($pid === 0) {

                Resque::setBackend($master->redisBackend);
                $progress->loadIncludes();

                $worker = new ResqueWorker($progress->queues, new ResqueTrigger());
                $worker->work();

                return ;
            }
        }
    }

    Resque::setBackend($master->redisBackend);
    $worker = new ResqueWorker(['resque-signal'], new ResqueTrigger());
    $worker->work();
}
else
{
    $starter = new ResqueLauncher($argv[0], $master);
    $starter->handle($cmd);
}




