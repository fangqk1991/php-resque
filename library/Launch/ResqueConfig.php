<?php

namespace FC\Resque\Launch;

class ResqueConfig
{
    public $redisBackend;
    public $queues;
    public $includes;
    public $logFile;
    public $pidFile;

    public function __construct()
    {
        $this->redisBackend = NULL;
        $this->queues = array();
        $this->includes = array();
        $this->logFile = NULL;
        $this->pidFile = NULL;
    }

    public function fc_generate($data)
    {
        $propertyMap = $this->fc_propertyMapper();
        foreach($propertyMap as $property => $jsonKey)
        {
            if(isset($data[$jsonKey]) && property_exists($this, $property))
            {
                $this->$property = $data[$jsonKey];
            }
        }

        $this->checkValid();
    }

    public function checkValid()
    {
        if(empty($this->redisBackend)) {
            die("redisBackend error.\n");
        }

        if(!is_array($this->queues) || count($this->queues) === 0) {
            die("queues error.\n");
        }

        foreach ($this->includes as $file)
        {
            if(!file_exists($file)) {
                die("$file not exists.\n");
            }
        }
    }

    private function fc_propertyMapper()
    {
        return array(
            'redisBackend' => 'redis',
            'queues' => 'queues',
            'includes' => 'includes',
            'logFile' => 'logFile',
            'pidFile' => 'pidFile',
        );
    }

    public static function configFromFile($configFile)
    {
        $content = file_get_contents($configFile);
        $data = json_decode($content, TRUE);
        $config = new self();
        $config->fc_generate($data);
        return $config;
    }
}
