<?php

namespace FC\Resque\Launch;

use FC\Utils\Model\Model;

class FCLeader extends Model
{
    public $name;
    public $queues;
    public $includes;
    public $workerCount;

    protected function fc_defaultInit()
    {
        $this->name = NULL;
        $this->queues = array();
        $this->includes = array();
        $this->workerCount = 1;
    }

    protected function fc_afterGenerate($data = array())
    {
        if(empty($this->name)) {
            die(__CLASS__ . " name error.\n");
        }

        if(empty($this->queues)) {
            die(__CLASS__ . " queues error.\n");
        }

        if(!is_int($this->workerCount) || $this->workerCount <= 0) {
            die(__CLASS__ . " workerCount error.\n");
        }

        foreach ($this->includes as $file)
        {
            if(!file_exists($file)) {
                die(__CLASS__ . " $file not exists.\n");
            }
        }
    }

    protected function fc_propertyMapper()
    {
        return array(
            'name' => 'name',
            'queues' => 'queues',
            'includes' => 'includes',
            'workerCount' => 'workerCount',
        );
    }

    public function loadIncludes()
    {
        foreach ($this->includes as $file)
        {
            include_once $file;
        }
    }
}
