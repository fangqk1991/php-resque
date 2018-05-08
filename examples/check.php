<?php

require_once __DIR__ . '/../vendor/autoload.php';

use \FC\Resque\MyResque;

MyResque::init('127.0.0.1', 6488);