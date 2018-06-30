<?php

namespace FC\Resque;

use Exception;
use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueStat;
use FC\Resque\Job\DirtyExitException;
use FC\Resque\Job\JobStatus;

class ResqueWorker
{
	/**
	* @var IResqueTrigger
	*/
	private $_trigger;

	/**
	 * @var array Array of all associated queues for this worker.
	 */
	private $_queues = array();

	private $_shutdown = false;

	private $_id;

	/**
	 * @var ResqueJob
	 */
	private $_currentJob = null;
	private $_childPID = null;

    public function __construct(array $queues, IResqueTrigger $trigger = NULL)
    {
        $this->_queues = $queues;
        $this->_id = php_uname('n') . ':'.getmypid() . ':' . implode(',', $this->_queues);

        $this->_trigger = $trigger;
    }

	public static function exists($workerId)
	{
		return Resque::redis()->sismember('resque:workers', $workerId);
	}

    public function setId($workerId)
    {
        $this->_id = $workerId;
    }

    public function getId()
    {
        return $this->_id;
    }

	public function work()
	{
        if($this->_trigger)
            $this->_trigger->onWorkerStart($this);

        $this->registerWorker();

		while(true) {

			if($this->_shutdown) {
				break;
			}

            $job = ResqueJob::reserveBlocking($this->queues());

			if(!($job instanceof ResqueJob)) {
				continue;
			}

            if($this->_trigger)
                $this->_trigger->onJobFound($job);

			$this->workingOn($job);

			$this->_childPID = Resque::fork();

			// Forked and we're the child. Run the job.
			if ($this->_childPID === 0 || $this->_childPID === false) {
                if($this->_trigger)
                    $this->_trigger->onJobPerform($job);

				$this->perform($job);
				if ($this->_childPID === 0) {
					exit(0);
				}
			}

			if($this->_childPID > 0) {

                if($this->_trigger)
                    $this->_trigger->onSalveCreated($this->_childPID);

				// Wait until the child process finishes before continuing
				pcntl_wait($status);
				$exitStatus = pcntl_wexitstatus($status);

				if($exitStatus !== 0) {
					$job->fail(new DirtyExitException(
						'Job exited with exit code ' . $exitStatus
					));
				}
			}

			$this->_childPID = null;
			$this->doneWorking();
		}

		$this->unregisterWorker();
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
	 * Schedule a worker for shutdown. Will finish processing the current job
	 * and when the timeout interval is reached, the worker will shut down.
	 */
	public function shutdown()
	{
        if($this->_trigger)
            $this->_trigger->onSignalReceived(__FUNCTION__);

		$this->_shutdown = true;
	}

	/**
	 * Force an immediate shutdown of the worker, killing any child jobs
	 * currently running.
	 */
	public function shutdownNow()
	{
		$this->shutdown();
		$this->killChild();
	}

	/**
	 * Kill a forked child job immediately. The job it is processing will not
	 * be completed.s
	 */
	public function killChild()
	{
		if(!$this->_childPID) {
            if($this->_trigger)
                $this->_trigger->onSignalReceived(__FUNCTION__ . ' No child to kill.');
			return;
		}

        if($this->_trigger)
            $this->_trigger->onSignalReceived(__FUNCTION__ . ' Killing child at ' . $this->_childPID);

		if(exec('ps -o pid,state -p ' . $this->_childPID, $output, $returnCode) && $returnCode != 1) {
            if($this->_trigger)
                $this->_trigger->onSignalReceived(__FUNCTION__ . sprintf(' Child[%s] found, killing.', $this->_childPID));
			posix_kill($this->_childPID, SIGKILL);
			$this->_childPID = null;
		}
		else {
            if($this->_trigger)
                $this->_trigger->onSignalReceived(__FUNCTION__ . sprintf(' Child[%s] not found, restarting.', $this->_childPID));
			$this->shutdown();
		}
	}

	/**
	 * Register this worker in Redis.
	 */
	public function registerWorker()
	{
		Resque::redis()->sadd('resque:workers', $this->_id);
		Resque::redis()->set('resque:worker:' . $this->_id . ':started', strftime('%a %b %d %H:%M:%S %Z %Y'));
	}

	/**
	 * Unregister this worker in Redis. (shutdown etc)
	 */
	public function unregisterWorker()
	{
		if(is_object($this->_currentJob)) {
			$this->_currentJob->fail(new DirtyExitException());
		}

		$id = $this->_id;
		Resque::redis()->srem('resque:workers', $id);
		Resque::redis()->del('resque:worker:' . $id);
		Resque::redis()->del('resque:worker:' . $id . ':started');
		ResqueStat::clear('processed:' . $id);
        ResqueStat::clear('failed:' . $id);
	}

	/**
	 * Tell Redis which job we're currently working on.
	 */
	public function workingOn(ResqueJob $job)
	{
		$job->worker = $this;
		$this->_currentJob = $job;
		$job->updateStatus(JobStatus::STATUS_RUNNING);
		$data = json_encode(array(
			'queue' => $job->queue,
			'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
			'payload' => $job->payload
		));
		Resque::redis()->set('resque:worker:' . $job->worker->getId(), $data);
	}

	public function doneWorking()
	{
		$this->_currentJob = null;
        ResqueStat::incr('processed');
		ResqueStat::incr('processed:' . $this->getId());
		Resque::redis()->del('resque:worker:' . $this->getId());
	}

	public function job()
	{
		$job = Resque::redis()->get('resque:worker:' . $this->_id);
		if(!$job) {
			return array();
		}
		else {
			return json_decode($job, true);
		}
	}
}
