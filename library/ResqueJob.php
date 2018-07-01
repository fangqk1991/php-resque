<?php

namespace FC\Resque;

use Exception;
use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueException;
use FC\Resque\Core\ResqueStat;
use FC\Resque\Job\IResqueTask;
use FC\Resque\Job\JobStatus;

class ResqueJob
{
	/**
	 * @var string The name of the queue that this job belongs to.
	 */
	public $queue;

	/**
	 * @var array Array containing details of the job.
	 */
	public $payload;

	/**
	 * @var object|IResqueTask Instance of the class performing work for this job.
	 */
	private $instance;

	private $_workerID;

	public function __construct($queue, $payload, $workerID)
	{
		$this->queue = $queue;
		$this->payload = $payload;
		$this->_workerID = $workerID;
	}

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 * @param boolean $monitor Set to true to be able to monitor the status of a job.
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public static function create($queue, $class, $args = null, $monitor = false)
	{
        $id = md5(uniqid('', TRUE));

		if(!is_array($args)) {
			throw new \InvalidArgumentException(
				'Supplied $args must be an array.'
			);
		}
		Resque::push($queue, array(
			'class'	=> $class,
			'args'	=> $args,
			'id'	=> $id,
			'queue_time' => microtime(true),
		));

		if($monitor) {
			JobStatus::create($id);
		}

		return $id;
	}

	/**
	 * Update the status of the current job.
	 *
	 * @param int $status Status constant from Resque_Job_Status indicating the current status of a job.
	 */
	public function updateStatus($status)
	{
		if(empty($this->payload['id'])) {
			return;
		}

		$statusInstance = new JobStatus($this->payload['id']);
		$statusInstance->update($status);
	}

	/**
	 * Return the status of the current job.
	 *
	 * @return int The status of the job as one of the Resque_Job_Status constants.
	 */
	public function getStatus()
	{
		$status = new JobStatus($this->payload['id']);
		return $status->get();
	}

	/**
	 * Get the arguments supplied to this job.
	 *
	 * @return array Array of arguments.
	 */
	public function getArguments()
	{
		if (!isset($this->payload['args'])) {
			return array();
		}

		return $this->payload['args'];
	}

	/**
	 * Get the instantiated object for this job that will be performing work.
	 * @return IResqueTask Instance of the object that this job belongs to.
	 * @throws ResqueException
	 */
	public function getInstance()
	{
		if (is_null($this->instance)) {

		    $className = $this->payload['class'];

            if (!class_exists($className) || !(new $className instanceof IResqueTask)) {
                throw new ResqueException(
                    'Could not find job class ' . $className . '.'
                );
            }

            $this->instance = new $className();
		}

        return $this->instance;
	}

	public function perform()
	{
        $this->getInstance()->perform($this->getArguments());
	}

	public function fail(Exception $exception)
	{
		$this->updateStatus(JobStatus::kStatusFailed);

        {
            $data = array(
                'failed_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
                'payload' => $this->payload,
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
                'backtrace' => explode("\n", $exception->getTraceAsString()),
                'worker' => $this->_workerID,
                'queue' => $this->queue
            );

            Resque::redis()->rpush('resque:failed', json_encode($data));
        }

		ResqueStat::incr('failed');
        ResqueStat::incr('failed:' . $this->_workerID);
	}

	/**
	 * Re-queue the current job.
	 * @return string
	 */
	public function recreate()
	{
		$status = new JobStatus($this->payload['id']);
		$monitor = false;
		if($status->isTracking()) {
			$monitor = true;
		}

		return self::create($this->queue, $this->payload['class'], $this->getArguments(), $monitor);
	}

	public function __toString()
	{
	    return $this->getDescription();
	}

	public function getDescription()
    {
        $name = array(
            'Job{' . $this->queue .'}'
        );
        if(!empty($this->payload['id'])) {
            $name[] = 'ID: ' . $this->payload['id'];
        }
        $name[] = $this->payload['class'];
        if(!empty($this->payload['args'])) {
            $name[] = json_encode($this->payload['args']);
        }
        return '(' . implode(' | ', $name) . ')';
    }
}
