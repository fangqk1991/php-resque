<?php

namespace FC\Resque\Core;

use Redis;
use RuntimeException;

class Resque
{
    const kVersion = '1.3';

    private $_redisBackend;
    private $_redis;

    private $_observers;

    private function __construct()
    {
        $this->_observers = array();
    }

    private static $_instance;

    private static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    private function __clone()
    {
        die('Clone is not allowed.' . E_USER_ERROR);
    }

    public static function setBackend($server)
    {
        $instance = self::getInstance();
        $instance->_redisBackend = $server;
        $instance->_redis = NULL;
    }

    public static function redis()
    {
        $instance = self::getInstance();

        if ($instance->_redis) {
            return $instance->_redis;
        }

        $redis = new Redis();
        list($host, $port) = explode(':', $instance->_redisBackend);
        $redis->connect($host, $port);
        $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);

        $instance->_redis = $redis;
        return $redis;
    }

    public static function fork()
    {
        // Close the connection to Redis before forking.
        // This is a workaround for issues phpredis has.

        $instance = self::getInstance();
        $instance->_redis = NULL;

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }

        return $pid;
    }

    public static function enqueue($queue, $class, $args = array())
    {
        return ResqueJob::create($queue, $class, $args);
    }

    public static function addObserver(IResqueObserver $observer)
    {
        array_push(self::getInstance()->_observers, $observer);
    }

    public static function observers()
    {
        return self::getInstance()->_observers;
    }
}
