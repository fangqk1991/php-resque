#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Core\Resque;
use FC\Resque\Launch\FCMaster;
use FC\Resque\Launch\FCWorker;
use FC\Resque\Launch\Launcher;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

$cmd = isset($argv[1]) ? $argv[1] : '';


$fcMaster = FCMaster::masterFromFile(__DIR__ . '/config.local/config.json');

if($cmd === '--launch')
{
    $fcMaster->checkLaunchAble();
    $fcMaster->clearDeadWorkers();

    foreach ($fcMaster->workers as $progress)
    {
        if($progress instanceof FCWorker)
        {
            $pid = $fcMaster->fork();

            if ($pid === 0) {

                Resque::setBackend($fcMaster->redisBackend);
                $progress->loadIncludes();

                $worker = new ResqueWorker($progress->queues, new ResqueTrigger());
                $worker->work();

                return ;
            }
        }
    }

    $fcMaster->savePIDInfos();
    $fcMaster->runMaster();
}
else
{
    $starter = new Launcher($argv[0], $fcMaster);
    $starter->handle($cmd);
}




