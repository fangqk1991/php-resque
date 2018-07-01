<?php

namespace FC\Resque;

use Exception;
use FC\Resque\Core\IResqueTrigger;
use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueStat;
use FC\Resque\Job\DirtyExitException;
use FC\Resque\Job\JobStatus;

class ResqueWorker
{
    private $_id;
	private $_queues = array();

    /**
     * @var IResqueTrigger
     */
    private $_trigger;

    public function __construct(array $queues)
    {
        $this->_queues = $queues;
        $this->_id = php_uname('n') . ':'.getmypid() . ':' . implode(',', $this->_queues);
    }

    public function setTrigger(IResqueTrigger $trigger)
    {
        $this->_trigger = $trigger;
    }

    public function getID()
    {
        return $this->_id;
    }

	public function work()
	{
        if($this->_trigger)
            $this->_trigger->onWorkerStart($this);

        $this->registerWorker();

		while(true) {

            $job = self::waitJob();

			if(!($job instanceof ResqueJob)) {
				continue;
			}

            if($this->_trigger)
                $this->_trigger->onJobFound($job);

			$this->workingOn($job);

			$pid = Resque::fork();

			// Forked and we're the child. Run the job.
			if ($pid === 0) {
                if($this->_trigger)
                    $this->_trigger->onJobPerform($job);

				$this->perform($job);
                exit(0);
			}

            if($this->_trigger)
                $this->_trigger->onSalveCreated($pid);

            // Wait until the child process finishes before continuing
            pcntl_wait($status);
            $exitStatus = pcntl_wexitstatus($status);

            if($exitStatus !== 0) {
                $job->fail(new DirtyExitException(
                    'Job exited with exit code ' . $exitStatus
                ));
            }

			$this->doneWorking();
		}
	}

	/**
	 * Process a single job.
	 *
	 * @param ResqueJob $job The job to be processed.
	 */
	public function perform(ResqueJob $job)
	{
		try {
			$job->perform();
		}
		catch(Exception $e) {
            if($this->_trigger)
                $this->_trigger->onJobFailed($job, $e);
			$job->fail($e);
			return;
		}

		$job->updateStatus(JobStatus::STATUS_COMPLETE);

        if($this->_trigger)
            $this->_trigger->onJobDone($job);
	}

	private function queues()
	{
		if(!in_array('*', $this->_queues)) {
			return $this->_queues;
		}

		return Resque::queues();
	}

	/**
	 * Register this worker in Redis.
	 */
	public function registerWorker()
	{
		Resque::redis()->sadd(self::redisKey_workerSet(), $this->_id);
		Resque::redis()->set($this->redisKey_workerStarted(), strftime('%a %b %d %H:%M:%S %Z %Y'));
	}

	/**
	 * Unregister this worker in Redis. (shutdown etc)
	 */
	public function unregisterWorker()
	{
		$id = $this->_id;
		Resque::redis()->srem(self::redisKey_workerSet(), $id);
        Resque::redis()->del($this->redisKey_workerInfo());
		Resque::redis()->del($this->redisKey_workerStarted());
		ResqueStat::clear('processed:' . $id);
        ResqueStat::clear('failed:' . $id);
	}

	public function workingOn(ResqueJob $job)
	{
		$job->worker = $this;
		$job->updateStatus(JobStatus::STATUS_RUNNING);
		$data = json_encode(array(
			'queue' => $job->queue,
			'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
			'payload' => $job->payload
		));
		Resque::redis()->set($this->redisKey_workerInfo(), $data);
	}

	public function doneWorking()
	{
        ResqueStat::incr('processed');
		ResqueStat::incr('processed:' . $this->getID());
        Resque::redis()->del($this->redisKey_workerInfo());
	}

	public function job()
	{
		$job = Resque::redis()->get($this->redisKey_workerInfo());
		if(!$job) {
			return array();
		}
		else {
			return json_decode($job, true);
		}
	}

    /**
     * Return allWorkers workers known to Resque as instantiated instances.
     * @return array
     */
    public static function allWorkers()
    {
        $items = Resque::redis()->smembers(self::redisKey_workerSet());
        if(!is_array($items)) {
            $items = array();
        }

        $workers = array_map(function($workerID) {
            list($hostname, $pid, $queues) = explode(':', $workerID, 3);
            $queues = explode(',', $queues);
            $worker = new self($queues);
            $worker->_id = $workerID;
            return $worker;
        }, $items);

        return $workers;
    }

    public static function redisKey_workerSet()
    {
        return 'resque:workers';
    }

    public function redisKey_workerInfo()
    {
        return 'resque:worker:' . $this->_id;
    }

    public function redisKey_workerStarted()
    {
        return $this->redisKey_workerInfo() . ':started';
    }

    private function waitJob()
    {
        $list = array_map(function ($queue) {
            return 'resque:queue:' . $queue;
        }, $this->queues());

        $arr = Resque::redis()->blpop($list, Resque::kTimeout);

        if(empty($arr)) {
            return NULL;
        }

        $queue = substr($arr[0], strlen('resque:queue:'));
        $payload = json_decode($arr[1], TRUE);

        return new ResqueJob($queue, $payload);
    }
}
