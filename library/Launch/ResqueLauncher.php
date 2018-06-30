<?php

namespace FC\Resque\Launch;

class ResqueLauncher
{
    private $_config;
    private $_launchFile;

    public function __construct($launchFile, ResqueConfig $config)
    {
        $this->_launchFile = $launchFile;
        $this->_config = $config;
    }

    public function pidFile()
    {
        return $this->_config->pidFile;
    }

    public function getPID()
    {
        $pid = 0;

        $pidFile = $this->pidFile();
        if(file_exists($pidFile))
        {
            $pid = intval(file_get_contents($pidFile));
        }

        return $pid;
    }

    public function start()
    {
        $pid = $this->getPID();
        if($pid > 0)
        {
            $this->println("php-resque(${pid}) is running.");
            $this->println('You should stop it before you start.');
            return ;
        }

        $this->println('Starting php-resque...');
        passthru(sprintf('nohup php "%s" --launch >> "%s" 2>&1 & %s echo $! > "%s"',
            $this->_launchFile, $this->_config->logFile, "\n", $this->pidFile()));
    }

    public function stop()
    {
        $pid = $this->getPID();
        if($pid === 0)
        {
            $this->println('php-resque is not running.');
            return ;
        }

        $this->println('Stopping php-resque...');

        posix_kill($pid, SIGKILL);
        unlink($this->pidFile());
    }

    public function checkStatus()
    {
        $pid = $this->getPID();
        if($pid > 0)
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