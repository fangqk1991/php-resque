#!/usr/bin/env php
<?php

include_once __DIR__ . '/../vendor/autoload.php';

use FC\Resque\Core\IResqueObserver;
use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueJob;
use FC\Resque\Core\ResqueWorker;
use FC\Resque\Launch\FCLauncher;

class ResqueObserver implements IResqueObserver
{
    public function onWorkerStart(ResqueWorker $worker)
    {
        // TODO: Implement onWorkerStart() method.
    }

    public function onJobFound(ResqueJob $job)
    {
        // TODO: Implement onJobFound() method.
    }

    public function onJobPerform(ResqueJob $job)
    {
        // TODO: Implement onJobPerform() method.
    }

    public function onJobDone(ResqueJob $job)
    {
        // TODO: Implement onJobDone() method.
    }

    public function onJobFailed(ResqueJob $job, Exception $e)
    {
        // TODO: Implement onJobFailed() method.
    }

    public function onSalveCreated($pid)
    {
        // TODO: Implement onSalveCreated() method.
    }
}

$launchFile = $argv[0];
$cmd = isset($argv[1]) ? $argv[1] : '';

//// 可通过此方法添加全局观察者
//Resque::addObserver(new ResqueObserver());

// 配置文件中的 ${__DIR__} 代表配置文件所在的文件目录
$launcher = new FCLauncher($launchFile, __DIR__ . '/resque-demo.json');
$launcher->handle($cmd);

