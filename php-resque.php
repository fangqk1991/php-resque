#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Launch\FCMaster;
use FC\Resque\Launch\Launcher;

$cmd = isset($argv[1]) ? $argv[1] : '';

$fcMaster = FCMaster::masterFromFile(__DIR__ . '/config.local/config.json');

if($cmd === '--launch')
{
    $fcMaster->run();
}
else
{
    $starter = new Launcher($argv[0], $fcMaster);
    $starter->handle($cmd);
}




