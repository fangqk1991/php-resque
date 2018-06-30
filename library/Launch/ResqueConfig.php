<?php

namespace FC\Resque\Launch;

class ResqueConfig extends Model
{
    public $redisBackend;
    public $masterLogFile;
    public $masterPIDFile;

    public $progresses;

    protected function fc_defaultInit()
    {
        $this->redisBackend = NULL;
        $this->masterLogFile = NULL;
        $this->masterPIDFile = NULL;

        $this->progresses = array();
    }

    protected function fc_afterGenerate($data = array())
    {
        if(empty($this->redisBackend)) {
            die(__CLASS__ . " redisBackend error.\n");
        }

        if(empty($this->masterLogFile)) {
            die(__CLASS__ . " masterLogFile error.\n");
        }

        if(empty($this->masterPIDFile)) {
            die(__CLASS__ . " masterPIDFile error.\n");
        }
    }

    protected function fc_propertyMapper()
    {
        return array(
            'redisBackend' => 'redis',
            'masterLogFile' => 'masterLogFile',
            'masterPIDFile' => 'masterPIDFile',
            'progresses' => 'progresses',
        );
    }

    protected function fc_arrayItemClassMapper()
    {
        return array(
            'progresses' => '\FC\Resque\Launch\Progress'
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
