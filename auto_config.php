<?php

global $__auto_config;
$__auto_config = [
    'type'               => 'mysql', // only accept mysql and pgsql string
    'host'               => 'localhost',
    'database'           => 'gsa',
    'username'           => 'root',
    'pass'               => '',
];

$path = __DIR__ . "/src/";

require_once $path."Auto.php";