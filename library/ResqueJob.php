<?php

namespace FC\Resque;

use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueStat;
use FC\Resque\Job\FailureJob;
use FC\Resque\Job\IResqueTask;
use FC\Resque\Job\JobStatus;

class ResqueJob
{
	/**
	 * @var string The name of the queue that this job belongs to.
	 */
	public $queue;

	/**
	 * @var ResqueWorker Instance of the Resque worker running this job.
	 */
	public $worker;

	/**
	 * @var array Array containing details of the job.
	 */
	public $payload;

	/**
	 * @var object|IResqueTask Instance of the class performing work for this job.
	 */
	private $instance;

	/**
	 * Instantiate a new instance of a job.
	 *
	 * @param string $queue The queue that the job belongs to.
	 * @param array $payload array containing details of the job.
	 */
	public function __construct($queue, $payload)
	{
		$this->queue = $queue;
		$this->payload = $payload;
	}

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 * @param boolean $monitor Set to true to be able to monitor the status of a job.
	 * @param string $id Unique identifier for tracking the job. Generated if not supplied.
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public static function create($queue, $class, $args = null, $monitor = false, $id = null)
	{
		if (is_null($id)) {
			$id = Resque::generateJobId();
		}

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

    public static function reserveBlocking(array $queues)
	{
		$item = Resque::blpop($queues, Resque::kTimeout);

		if(!is_array($item)) {
			return NULL;
		}

		return new ResqueJob($item['queue'], $item['payload']);
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

	/**
	 * Mark the current job as having failed.
	 *
	 * @param $exception
	 */
	public function fail($exception)
	{
		$this->updateStatus(JobStatus::STATUS_FAILED);
		FailureJob::create(
			$exception,
			$this->worker,
			$this->queue,
            $this->payload
		);
		ResqueStat::incr('failed');
        ResqueStat::incr('failed:' . $this->worker->getId());
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
