<?php

require_once __DIR__ . '/vendor/chrisboulton/php-resque/lib/Resque.php';
require_once __DIR__ . '/vendor/chrisboulton/php-resque/lib/Resque/Worker.php';

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

$logLevel = Resque_Worker::LOG_NONE;
foreach ($includes as $file)
{
    if(!file_exists($file)) {
        die("config.json error: $file not exists.\n");
    }

    require_once $file;
}

$worker = new Resque_Worker($queues);
$worker->logLevel = $logLevel;
fwrite(STDOUT, '*** Starting worker '.$worker."\n");
$worker->work();





