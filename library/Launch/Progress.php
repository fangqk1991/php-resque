<?php

namespace FC\Resque\Launch;

class Progress extends Model
{
    public $name;
    public $queues;
    public $includes;

    protected function fc_defaultInit()
    {
        $this->name = NULL;
        $this->queues = array();
        $this->includes = array();
    }

    protected function fc_afterGenerate($data = array())
    {
        if(empty($this->name)) {
            die(__CLASS__ . " name error.\n");
        }

        if(empty($this->queues)) {
            die(__CLASS__ . " queues error.\n");
        }

        if(empty($this->includes)) {
            die(__CLASS__ . " includes error.\n");
        }
    }

    protected function fc_propertyMapper()
    {
        return array(
            'name' => 'name',
            'queues' => 'queues',
            'includes' => 'includes',
        );
    }
}
