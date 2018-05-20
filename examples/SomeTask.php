<?php

namespace FC\Example;

require_once __DIR__ . '/../vendor/autoload.php';

use \FC\Resque\ResqueTaskBase;

class SomeTask extends ResqueTaskBase
{
    public function myTask($params)
    {
        $msg = 'SomeTask: ' . date('Y-m-d H:i:s');
        echo "$msg\n";
        shell_exec(sprintf('echo "%s" >> %s/example.txt', $msg, __DIR__));
    }

    public function onException(\Exception $e)
    {
        parent::onException($e); // TODO: Change the autogenerated stub
    }
}