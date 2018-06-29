<?php

namespace FC\Resque\Launch;

class ResqueLauncher
{
    private $_configFile;
    private $_config;

    public function __construct($configFile)
    {
        $this->_configFile = $configFile;

        $content = file_get_contents($configFile);
        $this->_config = json_decode($content, TRUE);
    }

    public function pidFile()
    {
        return $this->_config['pidFile'];
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
        passthru(sprintf('nohup php "%s/php-resque.php" "%s" >> "%s" 2>&1 & %s echo $! > "%s"',
            __DIR__, $this->_configFile, $this->_config['logFile'], "\n", $this->pidFile()));
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

    private function println($msg)
    {
        fwrite(STDOUT, sprintf('%s%s', $msg, PHP_EOL));
    }
}