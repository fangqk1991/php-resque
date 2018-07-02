<?php

namespace FC\Resque\Core;

use FC\Resque\Job\IResqueTask;
use InvalidArgumentException;

class ResqueJob
{
	public $queue;
	public $payload;

	public function __construct($queue, $payload)
	{
		$this->queue = $queue;
		$this->payload = $payload;

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

    public function addToQueue()
    {
        Resque::push($this->queue, $this->payload);
    }

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 */
	public static function create($queue, $class, array $args)
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

		$job = new ResqueJob($queue, $payload);
		$job->addToQueue();

		return $job;
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
		return self::create($this->queue, $this->getClassName(), $this->getArguments());
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
