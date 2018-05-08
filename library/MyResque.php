<?php

namespace FC\Resque;

class MyResque
{
    private static $_instance = NULL;

    private $host;
    private $port;

    private function __construct()
    {
    }

    private static function getInstance()
    {
        if(!(self::$_instance instanceof self))
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    private function __clone()
    {
        die('Clone is not allowed.' . E_USER_ERROR);
    }

    public static function init($host, $port)
    {
        $obj = self::getInstance();
        $obj->host = $host;
        $obj->port = $port;

        \Resque::setBackend(sprintf('%s:%s', $obj->host, $obj->port));
    }

	public static function enqueue($queue, $task, $args)
	{
        \Resque::enqueue($queue, $task, $args, true);
	}
}