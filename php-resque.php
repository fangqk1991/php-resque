#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Launch\Launcher;
use FC\Resque\Launch\FCWorker;
use FC\Resque\Resque;
use FC\Resque\ResqueMaster;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

$cmd = isset($argv[1]) ? $argv[1] : '';


$master = new ResqueMaster(new ResqueTrigger());
$master->initWithConfig(__DIR__ . '/config.local/config.json');
$masterProgress = $master->progress();

Resque::setBackend($master->redisBackend());

if($cmd === '--launch')
{
    $master->checkLaunchAble();

    $master->clearDeadWorkers();

    foreach ($masterProgress->workers as $progress)
    {
        if($progress instanceof FCWorker)
        {
            $pid = $masterProgress->fork();

            if ($pid === 0) {

                Resque::setBackend($masterProgress->redisBackend);
                $progress->loadIncludes();

                $worker = new ResqueWorker($progress->queues, new ResqueTrigger());
                $worker->work();

                return ;
            }
        }
    }

    $masterProgress->savePIDInfos();

    $master->work();
}
else
{
    $starter = new Launcher($argv[0], $masterProgress);
    $starter->handle($cmd);
}




