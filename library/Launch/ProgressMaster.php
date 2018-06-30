<?php

namespace FC\Resque\Launch;

use FC\Utils\Model\Model;
use RuntimeException;

class ProgressMaster extends Model
{
    public $redisBackend;
    public $logFile;
    public $pidFile;

    public $progresses;

    private $_curPID;
    private $_subPIDs;

    protected function fc_defaultInit()
    {
        $this->redisBackend = NULL;
        $this->logFile = NULL;
        $this->pidFile = NULL;

        $this->progresses = array();

        $this->_curPID = 0;
        $this->_subPIDs = array();
    }

    protected function fc_afterGenerate($data = array())
    {
        if(empty($this->redisBackend)) {
            die(__CLASS__ . " redisBackend error.\n");
        }

        if(empty($this->logFile)) {
            die(__CLASS__ . " masterLogFile error.\n");
        }

        if(empty($this->pidFile)) {
            die(__CLASS__ . " masterPIDFile error.\n");
        }

        $this->loadPIDInfos();
    }

    private function loadPIDInfos()
    {
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
            'progresses' => 'progresses',
        );
    }

    protected function fc_arrayItemClassMapper()
    {
        return array(
            'progresses' => '\FC\Resque\Launch\ProgressWorker'
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

    public function checkLaunchAble()
    {
        if($this->_curPID > 0)
        {
            die("php-resque is running. Master PID: $this->_curPID.\n");
        }
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
    }
}
