<?php

namespace FC\Resque\Job;

use FC\Resque\Resque;
use FC\Resque\ResqueWorker;
use stdClass;

class FailureJob implements IFailureJob
{
	/**
	 * Initialize a failed job class and save it (where appropriate).
	 *
	 * @param object $payload Object containing details of the failed job.
	 * @param object $exception Instance of the exception that was thrown by the failed job.
	 * @param ResqueWorker $worker Instance of ResqueWorker that received the job.
	 * @param string $queue The name of the queue the job was fetched from.
	 */
	public function __construct($payload, $exception, ResqueWorker $worker, $queue)
	{
		$data = new stdClass;
		$data->failed_at = strftime('%a %b %d %H:%M:%S %Z %Y');
		$data->payload = $payload;
		$data->exception = get_class($exception);
		$data->error = $exception->getMessage();
		$data->backtrace = explode("\n", $exception->getTraceAsString());
		$data->worker = $worker->getId();
		$data->queue = $queue;
		$data = json_encode($data);
		Resque::redis()->rpush('resque:failed', $data);
	}
}
