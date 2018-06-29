<?php

namespace FC\Resque\Launch;

class ResqueConfig
{
    public $redisBackend;
    public $queues;
    public $includes;
    public $logFile;
    public $pidFile;
    public $launcher;

    public function __construct()
    {
        $this->redisBackend = NULL;
        $this->queues = array();
        $this->includes = array();
        $this->logFile = NULL;
        $this->pidFile = NULL;
        $this->launcher = NULL;
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
    }

    private function fc_propertyMapper()
    {
        return array(
            'redisBackend' => 'redis',
            'queues' => 'queues',
            'includes' => 'includes',
            'logFile' => 'logFile',
            'pidFile' => 'pidFile',
            'launcher' => 'launcher',
        );
    }
}
