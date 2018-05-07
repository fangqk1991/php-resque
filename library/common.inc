<?php

$parent = __DIR__;
while (!@file_exists($parent. ' /vendor/autoload.php')) {
    if (!@file_exists($parent)) {
        break;
    }
    if ($parent === dirname($parent)) {
        echo "vendor/autoload.php not found\n";
        exit(1);
    }

    $parent = dirname($parent);
}

require_once $parent . '/vendor/autoload.php';
