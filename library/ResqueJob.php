<?php

namespace FC\Resque;

use Exception;
use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueException;
use FC\Resque\Core\ResqueStat;
use FC\Resque\Job\IResqueTask;
use InvalidArgumentException;

class ResqueJob
{
    const kStatusWaiting = 1;
    const kStatusRunning = 2;
    const kStatusFailed = 3;
    const kStatusComplete = 4;

	public $queue;
	public $payload;
	private $_monitor;

	public function __construct($queue, $payload, $monitor = FALSE)
	{
		$this->queue = $queue;
		$this->payload = $payload;
		$this->_monitor = $monitor;

        if (!isset($this->payload['args'])) {
            $this->payload['args'] = array();
        }

        if (!isset($this->payload['id'])) {
            $this->payload['id'] = md5(uniqid('', TRUE));
        }
    }

    public function getArguments()
    {
        return $this->payload['args'];
    }

    public function getJobID()
    {
        return $this->payload['id'];
    }

    public function getClassName()
    {
        return $this->payload['class'];
    }

    private function redisKey_jobStatus()
    {
        return 'resque:job:' . $this->getJobID() . ':status';
    }

    public function addToQueue()
    {
        Resque::push($this->queue, $this->payload);

        if($this->_monitor)
        {
            $statusPacket = array(
                'status' => self::kStatusWaiting,
                'updated' => time(),
                'started' => time(),
            );
            Resque::redis()->set($this->redisKey_jobStatus(), json_encode($statusPacket));
        }
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
	 * @throws InvalidArgumentException
	 */
	public static function create($queue, $class, array $args, $monitor = false)
	{
		if(!is_array($args)) {
			throw new InvalidArgumentException(
				'Supplied $args must be an array.'
			);
		}

		$payload = array(
            'class'	=> $class,
            'args'	=> $args,
            'id'	=> md5(uniqid('', TRUE)),
            'queue_time' => microtime(true),
        );

		$job = new ResqueJob($queue, $payload, $monitor);
		$job->addToQueue();

		return $job;
	}

	/**
	 * Update the status of the current job.
	 *
	 * @param int $status Status constant from Resque_Job_Status indicating the current status of a job.
	 */
	public function updateStatus($status)
	{
	    if($this->_monitor)
        {
            $statusPacket = array(
                'status' => $status,
                'updated' => time(),
            );
            Resque::redis()->set($this->redisKey_jobStatus(), json_encode($statusPacket));

            // Expire the status for completed jobs after 24 hours
            if($status === self::kStatusFailed || $status === self::kStatusComplete)
            {
                Resque::redis()->expire($this->redisKey_jobStatus(), 86400);
            }
        }
	}

	/**
	 * Return the status of the current job.
	 *
	 * @return int The status of the job as one of the Resque_Job_Status constants.
	 */
	public function getStatus()
	{
        $statusPacket = json_decode(Resque::redis()->get($this->redisKey_jobStatus()), true);
        if(!$statusPacket) {
            return FALSE;
        }

        return intval($statusPacket['status']);
	}

	public function perform()
	{
        $className = $this->getClassName();

        if (!class_exists($className)) {
            throw new ResqueException(
                'Could not find job class ' . $className . '.'
            );
        }

        $task = new $className();
        if(!($task instanceof IResqueTask))
        {
            throw new ResqueException(
                $className . ' do not implements IResqueTask.'
            );
        }

        $task->perform($this->getArguments());
	}

	/**
	 * Re-queue the current job.
	 * @return string
	 */
	public function recreate()
	{
		return self::create($this->queue, $this->getClassName(), $this->getArguments(), $this->_monitor);
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
        $name[] = 'ID: ' . $this->getJobID();
        $name[] = $this->getClassName();
        $name[] = json_encode($this->getArguments());

        return '(' . implode(' | ', $name) . ')';
    }
}
