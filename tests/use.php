<?php

require "../auto_config.php";

print_r(
    PhpAutoWhere\Auto::where()->table("ticket")->columns(['id'=>'text'])->render(["id"=>1])
);