<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MyConfigEx.php';

use FC\Resque\Core\Resque;

class MyResqueEx extends Resque
{
}

Resque::setBackend(MyConfigEx::Resque_RedisEnd);

