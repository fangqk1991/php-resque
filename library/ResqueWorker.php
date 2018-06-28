<?php

namespace FC\Resque;

use Exception;
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
	private $_paused = false;

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

	/**
	 * Return all workers known to Resque as instantiated instances.
	 * @return array
	 */
	public static function all()
	{
		$workers = Resque::redis()->smembers('resque:workers');
		if(!is_array($workers)) {
			$workers = array();
		}

		$instances = array();
		foreach($workers as $workerId) {
			$instances[] = self::find($workerId);
		}
		return $instances;
	}

	/**
	 * Given a worker ID, check if it is registered/valid.
	 *
	 * @param string $workerId ID of the worker.
	 * @return boolean True if the worker exists, false if not.
	 */
	public static function exists($workerId)
	{
		return (bool)Resque::redis()->sismember('resque:workers', $workerId);
	}

	/**
	 * Given a worker ID, find it and return an instantiated worker class for it.
	 *
	 * @param string $workerId The ID of the worker.
	 * @return bool|ResqueWorker
	 */
	public static function find($workerId)
	{
		if(!self::exists($workerId) || false === strpos($workerId, ":")) {
			return false;
		}

		list($hostname, $pid, $queues) = explode(':', $workerId, 3);
		$queues = explode(',', $queues);
		$worker = new self($queues);
		$worker->setId($workerId);
		return $worker;
	}

    public function setId($workerId)
    {
        $this->_id = $workerId;
    }

    public function getId()
    {
        return $this->_id;
    }

	/**
	 * The primary loop for a worker which when called on an instance starts
	 * the worker's life cycle.
	 *
	 * Queues are checked every $interval (seconds) for new jobs.
	 *
	 * @param int $interval How often to check for new jobs across the queues.
	 */
	public function work()
	{
        if($this->_trigger)
            $this->_trigger->onMasterStart($this);

		$this->startup();

		while(true) {
			if($this->_shutdown) {
				break;
			}

			$job = NULL;
			if(!$this->_paused) {
				$job = $this->reserve();
			}

			if(!$job) {
                fwrite(STDOUT, '*** no job ' . PHP_EOL);
				continue;
			}

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

	public function reserve()
	{
		$queues = $this->queues();
		if(!is_array($queues)) {
			return NULL;
		}

        $job = ResqueJob::reserveBlocking($queues);
        if($job instanceof ResqueJob) {
            if($this->_trigger)
                $this->_trigger->onJobFound($job);

            return $job;
        }

        return NULL;
	}

	private function queues()
	{
		if(!in_array('*', $this->_queues)) {
			return $this->_queues;
		}

		return Resque::queues();
	}

	/**
	 * Perform necessary actions to start a worker.
	 */
	private function startup()
	{
		$this->registerSigHandlers();
		$this->pruneDeadWorkers();
		$this->registerWorker();
	}

	/**
	 * Register signal handlers that a worker should respond to.
	 *
	 * TERM: Shutdown immediately and stop processing jobs.
	 * INT: Shutdown immediately and stop processing jobs.
	 * QUIT: Shutdown after the current job finishes processing.
	 * USR1: Kill the forked child immediately and continue processing jobs.
	 */
	private function registerSigHandlers()
	{
		if(!function_exists('pcntl_signal')) {
			return;
		}

		pcntl_signal(SIGTERM, array($this, 'shutDownNow'));
		pcntl_signal(SIGINT, array($this, 'shutDownNow'));
		pcntl_signal(SIGQUIT, array($this, 'shutdown'));
		pcntl_signal(SIGUSR1, array($this, 'killChild'));
		pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
		pcntl_signal(SIGCONT, array($this, 'resumeProcessing'));

		if($this->_trigger)
		    $this->_trigger->onSignalReceived(__FUNCTION__);
	}

	/**
	 * Signal handler callback for USR2, pauses processing of new jobs.
	 */
	public function pauseProcessing()
	{
        if($this->_trigger)
            $this->_trigger->onSignalReceived(__FUNCTION__);

		$this->_paused = true;
	}

	/**
	 * Signal handler callback for CONT, resumes worker allowing it to pick
	 * up new jobs.
	 */
	public function resumeProcessing()
	{
        if($this->_trigger)
            $this->_trigger->onSignalReceived(__FUNCTION__);

		$this->_paused = false;
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
	 * be completed.
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
	 * Look for any workers which should be running on this server and if
	 * they're not, remove them from Redis.
	 *
	 * This is a form of garbage collection to handle cases where the
	 * server may have been killed and the Resque workers did not die gracefully
	 * and therefore leave state information in Redis.
	 */
	public function pruneDeadWorkers()
	{
		$workerPids = $this->workerPids();
		$workers = self::all();
		foreach($workers as $worker)
		{
			if ($worker instanceof self)
			{
                list($hostname) = explode(':', $this->_id, 1);
				list($host, $pid, $queues) = explode(':', $worker->getId(), 3);
				if($host !== $hostname || in_array($pid, $workerPids) || $pid == getmypid()) {
					continue;
				}
                if($this->_trigger)
                    $this->_trigger->onSignalReceived(__FUNCTION__ . sprintf(' Pruning dead worker: %s', $worker->getId()));
				$worker->unregisterWorker();
			}
		}
	}

	/**
	 * Return an array of process IDs for all of the Resque workers currently
	 * running on this machine.
	 *
	 * @return array Array of Resque worker process IDs.
	 */
	public function workerPids()
	{
		$pids = array();
		exec('ps -A -o pid,command | grep [r]esque', $cmdOutput);
		foreach($cmdOutput as $line) {
			list($pids[],) = explode(' ', trim($line), 2);
		}
		return $pids;
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
		Stat::clear('processed:' . $id);
        Stat::clear('failed:' . $id);
	}

	/**
	 * Tell Redis which job we're currently working on.
	 *
	 * @param object $job Resque_Job instance containing the job we're working on.
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
        Stat::incr('processed');
		Stat::incr('processed:' . $this->getId());
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
