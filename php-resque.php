#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Launch\ProgressWorker;
use FC\Resque\Launch\ProgressMaster;
use FC\Resque\Launch\Launcher;
use FC\Resque\Resque;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

$cmd = isset($argv[1]) ? $argv[1] : '';

$master = ProgressMaster::masterFromFile(__DIR__ . '/config.local/config.json');

if($cmd === '--launch')
{
    $master->checkLaunchAble();

    foreach ($master->progresses as $progress)
    {
        if($progress instanceof ProgressWorker)
        {
            $pid = $master->fork();

            if ($pid === 0) {

                Resque::setBackend($master->redisBackend);
                $progress->loadIncludes();

                $worker = new ResqueWorker($progress->queues, new ResqueTrigger());
                $worker->work();

                return ;
            }
        }
    }

    $master->savePIDInfos();

    Resque::setBackend($master->redisBackend);
    $worker = new ResqueWorker(['resque-signal'], new ResqueTrigger());
    $worker->work();
}
else
{
    $starter = new Launcher($argv[0], $master);
    $starter->handle($cmd);
}




