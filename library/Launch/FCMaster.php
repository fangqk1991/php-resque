<?php

namespace FC\Resque\Launch;

use FC\Model\FCModel;
use FC\Resque\Core\Resque;
use FC\Resque\Core\ResqueWorker;
use FC\Resque\Schedule\ScheduleLeader;

class FCMaster extends FCModel
{
    public $name;
    public $redisBackend;
    public $logFile;
    public $pidFile;
    public $useSchedule;

    public $leaders;

    private $_curPID;
    private $_subPIDs;

    protected function fc_defaultInit()
    {
        $this->name = NULL;
        $this->redisBackend = NULL;
        $this->logFile = NULL;
        $this->pidFile = NULL;
        $this->useSchedule = FALSE;

        $this->leaders = array();
    }

    protected function fc_afterGenerate($data = array())
    {
        if(empty($this->name)) {
            die(__CLASS__ . " name error.\n");
        }

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
            'name' => 'name',
            'useSchedule' => 'useSchedule',
            'redisBackend' => 'redis',
            'logFile' => 'logFile',
            'pidFile' => 'pidFile',
            'leaders' => 'leaders',
        );
    }

    protected function fc_arrayItemClassMapper()
    {
        return array(
            'leaders' => '\FC\Resque\Launch\FCLeader'
        );
    }

    public static function masterFromFile($configFile)
    {
        $content = file_get_contents($configFile);
        $content = preg_replace('#\$\{__DIR__\}#', dirname($configFile), $content);
        $data = json_decode($content, TRUE);
        $config = new self();
        $config->fc_generate($data);
        return $config;
    }

    private function fork()
    {
        $pid = Resque::fork();

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

    private function savePIDInfos()
    {
        $pidList = $this->_subPIDs;
        array_unshift($pidList, posix_getpid());
        file_put_contents($this->pidFile, implode(' ', $pidList));
    }

    public function killPIDs()
    {
        foreach ($this->_subPIDs as $pid)
        {
            $ret = posix_kill($pid, SIGKILL);
            $this->println(sprintf('Kill sub progress(%d): %s', $pid, $ret ? 'succ.' : 'fail.'));
        }

        if($this->_curPID > 0)
        {
            $ret = posix_kill($this->_curPID, SIGKILL);
            $this->println(sprintf('Kill master progress(%d): %s', $pid, $ret ? 'succ.' : 'fail.'));
        }

        if(file_exists($this->pidFile))
        {
            unlink($this->pidFile);
        }

        $this->loadPIDInfos();
    }

    private function checkLaunchAble()
    {
        if(($pid = $this->curPID()) > 0)
        {
            die("The application is running. Master PID: $pid.\n");
        }
    }

    public function checkConfig()
    {
        if($this->useSchedule)
        {
            Resque::setBackend($this->redisBackend);

            // Please ignore this warning, redis config get can not input the $value.
            $result = Resque::redis()->config('GET', 'notify-keyspace-events');
            $str = $result['notify-keyspace-events'];
            if(strpos($str, 'E') === FALSE || strpos($str, 'x') === FALSE)
            {
                die("redis configurations error, please see https://fqk.io/about-timing-task/ \n");
            }
        }
    }

    private function clearDeadWorkers()
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

    public function run()
    {
        $this->checkConfig();
        $this->checkLaunchAble();
        $this->clearDeadWorkers();

        foreach ($this->leaders as $leader)
        {
            if($leader instanceof FCLeader)
            {
                for($i = 0; $i < $leader->workerCount; ++$i)
                {
                    if ($this->fork() === 0) {

                        Resque::setBackend($this->redisBackend);
                        $leader->loadIncludes();

                        $worker = new ResqueWorker($leader->queues);
                        $worker->work();

                        return ;
                    }
                }
            }
        }

        if($this->useSchedule)
        {
            if ($this->fork() === 0) {

                $leader = new ScheduleLeader($this->redisBackend);
                $leader->watch();

                return ;
            }
        }

        $this->savePIDInfos();

        $queue = array($this->name . '-MASTER');
        $worker = new ResqueWorker($queue);
        $worker->work();
    }

    private function println($msg)
    {
        fwrite(STDOUT, sprintf('%s%s', $msg, PHP_EOL));
    }
}
