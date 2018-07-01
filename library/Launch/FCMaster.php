<?php

namespace FC\Resque\Launch;

use FC\Resque\Core\Resque;
use FC\Resque\ResqueTrigger;
use FC\Resque\ResqueWorker;
use FC\Utils\Model\Model;

class FCMaster extends Model
{
    public $redisBackend;
    public $logFile;
    public $pidFile;

    public $workers;

    private $_curPID;
    private $_subPIDs;

    protected function fc_defaultInit()
    {
        $this->redisBackend = NULL;
        $this->logFile = NULL;
        $this->pidFile = NULL;

        $this->workers = array();
    }

    protected function fc_afterGenerate($data = array())
    {
        if(empty($this->redisBackend)) {
            die(__CLASS__ . " redisBackend error.\n");
        }

        if(empty($this->logFile)) {
            die(__CLASS__ . " logFile error.\n");
        }

        if(empty($this->pidFile)) {
            die(__CLASS__ . " pidFile error.\n");
        }

        $this->loadPIDInfos();
    }

    private function loadPIDInfos()
    {
        $this->_curPID = 0;
        $this->_subPIDs = array();

        $pids = array();

        if(file_exists($this->pidFile))
        {
            $content = file_get_contents($this->pidFile);
            if(preg_match_all('#\d+#', $content, $matches))
            {
                $pids = $matches[0];
            }
        }

        if(count($pids) > 0)
        {
            $this->_curPID = intval($pids[0]);

            array_shift($pids);
            $this->_subPIDs = array_map(function ($pid) {
                return intval($pid);
            }, $pids);
        }
    }

    public function curPID()
    {
        return $this->_curPID;
    }

    protected function fc_propertyMapper()
    {
        return array(
            'redisBackend' => 'redis',
            'logFile' => 'logFile',
            'pidFile' => 'pidFile',
            'workers' => 'workers',
        );
    }

    protected function fc_arrayItemClassMapper()
    {
        return array(
            'workers' => '\FC\Resque\Launch\FCWorker'
        );
    }

    public static function masterFromFile($configFile)
    {
        $content = file_get_contents($configFile);
        $data = json_decode($content, TRUE);
        $config = new self();
        $config->fc_generate($data);
        return $config;
    }

    public function fork()
    {
        $pid = pcntl_fork();

        if($pid === -1)
        {
            die("Unable to fork child worker.\n");
        }
        else if($pid > 0)
        {
            array_push($this->_subPIDs, $pid);
        }

        return $pid;
    }

    public function savePIDInfos()
    {
        $pidList = $this->_subPIDs;
        array_unshift($pidList, posix_getpid());
        file_put_contents($this->pidFile, implode(' ', $pidList));
    }

    public function stop()
    {
        foreach ($this->_subPIDs as $pid)
            posix_kill($pid, SIGKILL);

        if($this->_curPID > 0)
        {
            posix_kill($this->_curPID, SIGKILL);
        }

        if(file_exists($this->pidFile))
        {
            unlink($this->pidFile);
        }

        $this->loadPIDInfos();
    }

    public function checkLaunchAble()
    {
        if(($pid = $this->curPID()) > 0)
        {
            die("The application is running. Master PID: $pid.\n");
        }
    }

    public function clearDeadWorkers()
    {
        Resque::setBackend($this->redisBackend);

        $workers = ResqueWorker::allWorkers();
        foreach($workers as $worker)
        {
            if($worker instanceof ResqueWorker)
            {
                $worker->unregisterWorker();
            }
        }
    }

    public function runMaster()
    {
        $worker = new ResqueWorker(array('RESQUE-SIGNAL'), new ResqueTrigger());
        $worker->work();
    }
}
