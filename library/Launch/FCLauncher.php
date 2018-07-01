<?php

namespace FC\Resque\Launch;

class FCLauncher
{
    private $_master;
    private $_launchFile;

    public function __construct($launchFile, $configFile)
    {
        $this->_launchFile = $launchFile;
        $this->_master = FCMaster::masterFromFile($configFile);
    }

    public function pidFile()
    {
        return $this->_master->pidFile;
    }

    public function start()
    {
        if(($pid = $this->_master->curPID()) > 0)
        {
            $this->println("php-resque(${pid}) is running.");
            $this->println('You should stop it before you start.');
            return ;
        }

        $this->println('Starting php-resque...');
        passthru(sprintf('nohup php "%s" --launch >> "%s" 2>&1 &',
            $this->_launchFile, $this->_master->logFile));
    }

    public function stop()
    {
        if($this->_master->curPID() === 0)
        {
            $this->println('php-resque is not running.');
            return ;
        }

        $this->println('Stopping php-resque...');
        $this->_master->killPIDs();
    }

    public function checkStatus()
    {
        if(($pid = $this->_master->curPID()) > 0)
        {
            $this->println("php-resque(${pid}) is running.");
        }
        else
        {
            $this->println('php-resque is not running.');
        }
    }

    public function handle($cmd)
    {
        switch ($cmd)
        {
            case 'start':
                $this->start();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'restart':
                $this->stop();
                $this->start();
                break;
            case 'status':
                $this->checkStatus();
                break;
            case '--launch':
                $this->_master->run();
                break;
            default:
                $this->println('Usage: {start|stop|restart|status}');
                break;
        }
    }

    private function println($msg)
    {
        fwrite(STDOUT, sprintf('%s%s', $msg, PHP_EOL));
    }
}