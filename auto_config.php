<?php

define(
    "__auto_config",
    [
        'db'=> [
            'type'               => 'mysql', // only accept mysql and pgsql string
            'host'               => 'localhost',
            'database'           => 'gsa',
            'username'           => 'root',
            'pass'               => '',
        ],
        'app_date_format' =>       "d/m/Y",     // only support "d/m/Y" or "Y-m-d"
        'db_date_format' =>        "d/m/Y"      // only support "d/m/Y" or "Y-m-d"
    ]
);

$path = __DIR__ . "/src/";

require_once $path."Auto.php";