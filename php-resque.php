<?php

require_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Resque;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

$content = file_get_contents(__DIR__ . '/config.local/config.json');
$data = json_decode($content, TRUE);

$redisBackend = $data['redis'];
$queues = $data['queues'];
$includes = $data['includes'];

if(empty($redisBackend)) {
    die("config.json: redis error.\n");
}

if(!is_array($queues) || count($queues) === 0) {
    die("config.json: queues error.\n");
}

Resque::setBackend($redisBackend);

$logLevel = false;
foreach ($includes as $file)
{
    if(!file_exists($file)) {
        die("config.json error: $file not exists.\n");
    }

    require_once $file;
}

$worker = new ResqueWorker($queues, new ResqueTrigger());
$worker->work();


