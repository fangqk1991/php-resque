<?php

namespace FC\Resque\Job;

use FC\Resque\ResqueWorker;

interface IFailureJob
{
	/**
	 * Initialize a failed job class and save it (where appropriate).
	 *
	 * @param object $payload Object containing details of the failed job.
	 * @param object $exception Instance of the exception that was thrown by the failed job.
	 * @param ResqueWorker $worker Instance of ResqueWorker that received the job.
	 * @param string $queue The name of the queue the job was fetched from.
	 */
	public function __construct($payload, $exception, ResqueWorker $worker, $queue);
}
