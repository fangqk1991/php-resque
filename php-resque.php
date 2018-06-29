<?php

require_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Resque;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;

if(count($argv) <= 1)
{
    die("missing config file\n");
}

$content = file_get_contents($argv[1]);
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

foreach ($includes as $file)
{
    if(!file_exists($file)) {
        die("config.json error: $file not exists.\n");
    }

    require_once $file;
}

$worker = new ResqueWorker($queues, new ResqueTrigger());
$worker->work();


