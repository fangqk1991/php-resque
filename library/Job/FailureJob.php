<?php

namespace FC\Resque\Job;

use Exception;
use FC\Resque\Core\Resque;
use FC\Resque\ResqueWorker;
use stdClass;

class FailureJob
{
	public static function create(Exception $exception, ResqueWorker $worker, $queue, $payload)
    {

        $data = new stdClass;
        $data->failed_at = strftime('%a %b %d %H:%M:%S %Z %Y');
        $data->payload = $payload;
        $data->exception = get_class($exception);
        $data->error = $exception->getMessage();
        $data->backtrace = explode("\n", $exception->getTraceAsString());
        $data->worker = $worker->getID();
        $data->queue = $queue;
        $data = json_encode($data);
        Resque::redis()->rpush('resque:failed', $data);
    }
}
