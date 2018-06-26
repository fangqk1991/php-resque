<?php

namespace FC\Resque;

use Exception;

class Failure
{
	private static $backend;

	public static function create($payload, Exception $exception, ResqueWorker $worker, $queue)
	{
		$backend = self::getBackend();
		new $backend($payload, $exception, $worker, $queue);
	}

	public static function getBackend()
	{
		if(self::$backend === null) {
			self::$backend = '\FC\Resque\Job\FailureJob';
		}

		return self::$backend;
	}

	/**
	 * Set the backend to use for raised job failures. The supplied backend
	 * should be the name of a class to be instantiated when a job fails.
	 * It is your responsibility to have the backend class loaded (or autoloaded)
	 *
	 * @param string $backend The class name of the backend to pipe failures to.
	 */
	public static function setBackend($backend)
	{
		self::$backend = $backend;
	}
}