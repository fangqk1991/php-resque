<?php

namespace FC\Resque\Job;

use FC\Resque\Core\Resque;

class JobStatus
{
	const kStatusWaiting = 1;
	const kStatusRunning = 2;
	const kStatusFailed = 3;
	const kStatusComplete = 4;

	/**
	 * @var string The ID of the job this status class refers back to.
	 */
	private $id;

	/**
	 * @var mixed Cache variable if the status of this job is being monitored or not.
	 * 	True/false when checked at least once or null if not checked yet.
	 */
	private $isTracking = null;

	/**
	 * @var array Array of statuses that are considered final/complete.
	 */
	private static $completeStatuses = array(
		self::kStatusFailed,
		self::kStatusComplete
	);

	/**
	 * Setup a new instance of the job monitor class for the supplied job ID.
	 *
	 * @param string $id The ID of the job to manage the status for.
	 */
	public function __construct($id)
	{
		$this->id = $id;
	}

	/**
	 * Create a new status monitor item for the supplied job ID. Will create
	 * all necessary keys in Redis to monitor the status of a job.
	 *
	 * @param string $id The ID of the job to monitor the status of.
	 */
	public static function create($id)
	{
		$statusPacket = array(
			'status' => self::kStatusWaiting,
			'updated' => time(),
			'started' => time(),
		);
		Resque::redis()->set('resque:job:' . $id . ':status', json_encode($statusPacket));
	}

	/**
	 * Check if we're actually checking the status of the loaded job status
	 * instance.
	 *
	 * @return boolean True if the status is being monitored, false if not.
	 */
	public function isTracking()
	{
		if($this->isTracking === false) {
			return false;
		}

		if(!Resque::redis()->exists($this->redisKey_statusID())) {
			$this->isTracking = false;
			return false;
		}

		$this->isTracking = true;
		return true;
	}

	/**
	 * Update the status indicator for the current job with a new status.
	 *
	 * @param int The status of the job (see constants in Resque_Job_Status)
	 */
	public function update($status)
	{
		if(!$this->isTracking()) {
			return;
		}

		$statusPacket = array(
			'status' => $status,
			'updated' => time(),
		);
		Resque::redis()->set($this->redisKey_statusID(), json_encode($statusPacket));

		// Expire the status for completed jobs after 24 hours
		if($status === self::kStatusFailed || $status === self::kStatusComplete)
		{
			Resque::redis()->expire($this->redisKey_statusID(), 86400);
		}
	}

	/**
	 * Fetch the status for the job being monitored.
	 *
	 * @return mixed False if the status is not being monitored, otherwise the status as
	 * 	as an integer, based on the Resque_Job_Status constants.
	 */
	public function get()
	{
		if(!$this->isTracking()) {
			return false;
		}

		$statusPacket = json_decode(Resque::redis()->get($this->redisKey_statusID()), true);
		if(!$statusPacket) {
			return false;
		}

		return $statusPacket['status'];
	}

	/**
	 * Stop tracking the status of a job.
	 */
	public function stop()
	{
		Resque::redis()->del($this->redisKey_statusID());
	}

	/**
	 * Generate a string representation of this object.
	 *
	 * @return string String representation of the current job status class.
	 */
	public function __toString()
	{
		return 'resque:job:' . $this->id . ':status';
	}

	public function redisKey_statusID()
    {
        return 'resque:job:' . $this->id . ':status';
    }
}
