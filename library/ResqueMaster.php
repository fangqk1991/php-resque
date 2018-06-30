<?php

namespace FC\Resque;

use Exception;
use FC\Resque\Core\Resque;
use FC\Resque\Job\DirtyExitException;
use FC\Resque\Job\JobStatus;
use FC\Resque\Launch\FCMaster;
use FC\Resque\Stat\Stat;

class ResqueMaster extends ResqueWorker
{
    public function __construct(IResqueTrigger $trigger = NULL)
    {
        parent::__construct(array('RESQUE-SIGNAL'), $trigger);
    }

	public function clearDeadWorkers()
	{
		$workers = self::allWorkers();
		foreach($workers as $worker)
		{
		    if($worker instanceof ResqueWorker)
            {
                $worker->unregisterWorker();
            }
		}
	}

    /**
     * Return allWorkers workers known to Resque as instantiated instances.
     * @return array
     */
    public static function allWorkers()
    {
        $items = Resque::redis()->smembers('resque:workers');
        if(!is_array($items)) {
            $items = array();
        }

        $workers = array_map(function($workerID) {
            return self::find($workerID);
        }, $items);

        return $workers;
    }

    /**
     * Given a worker ID, find it and return an instantiated worker class for it.
     *
     * @param string $workerID The ID of the worker.
     * @return bool|ResqueWorker
     */
    public static function find($workerID)
    {
        if(!self::exists($workerID) || false === strpos($workerID, ":")) {
            return false;
        }

        list($hostname, $pid, $queues) = explode(':', $workerID, 3);
        $queues = explode(',', $queues);
        $worker = new ResqueWorker($queues);
        $worker->setId($workerID);
        return $worker;
    }

    /**
     * @var FCMaster
     */
    private $_progress;

    public function initWithConfig($configFile)
    {
        $this->_progress = FCMaster::masterFromFile($configFile);
    }

    public function progress()
    {
        return $this->_progress;
    }

    public function redisBackend()
    {
        return $this->_progress->redisBackend;
    }

    public function checkLaunchAble()
    {
        if(($pid = $this->_progress->curPID()) > 0)
        {
            die("The application is running. Master PID: $pid.\n");
        }
    }
}
