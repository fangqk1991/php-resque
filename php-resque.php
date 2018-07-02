#!/usr/bin/env php
<?php

include_once __DIR__ . '/vendor/autoload.php';

use FC\Resque\Launch\FCLauncher;

$launchFile = $argv[0];
$cmd = isset($argv[1]) ? $argv[1] : '';

$launcher = new FCLauncher($launchFile, __DIR__ . '/config.local/resque.config.json');
$launcher->handle($cmd);

