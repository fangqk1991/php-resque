<?php

namespace FC\Resque;

use Exception;
use FC\Resque\Core\IResqueTrigger;
use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueStat;
use FC\Resque\Job\DirtyExitException;

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

            $job = $this->waitJob();

			if(!($job instanceof ResqueJob)) {
				continue;
			}

            if($this->_trigger)
                $this->_trigger->onJobFound($job);

            {
                $job->updateStatus(ResqueJob::kStatusRunning);
                $data = json_encode(array(
                    'queue' => $job->queue,
                    'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
                    'payload' => $job->payload
                ));
                Resque::redis()->set($this->redisKey_workerInfo(), $data);
            }


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
                $this->onJobFailed($job, new DirtyExitException(
                    'Job exited with exit code ' . $exitStatus
                ));
            }

            ResqueStat::incr('processed');
            ResqueStat::incr('processed:' . $this->getID());
            Resque::redis()->del($this->redisKey_workerInfo());
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
			$this->onJobFailed($job, $e);
			return;
		}

		$job->updateStatus(ResqueJob::kStatusComplete);

        if($this->_trigger)
            $this->_trigger->onJobDone($job);
	}

	private function onJobFailed(ResqueJob $job, Exception $exception)
    {
        $job->updateStatus(ResqueJob::kStatusFailed);

        $data = array(
            'failed_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
            'payload' => $job->payload,
            'exception' => get_class($exception),
            'error' => $exception->getMessage(),
            'backtrace' => explode("\n", $exception->getTraceAsString()),
            'worker' => $this->getID(),
            'queue' => $job->queue
        );

        Resque::redis()->rpush('resque:failed', json_encode($data));

        ResqueStat::incr('failed');
        ResqueStat::incr('failed:' . $this->getID());

        if($this->_trigger)
            $this->_trigger->onJobFailed($job, $exception);
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
            list($hostname, $pid, $queuesStr) = explode(':', $workerID, 3);
            $queues = explode(',', $queuesStr);
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
