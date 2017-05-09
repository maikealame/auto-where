<?php

require "../auto_config.php";

print_r(
    Auto::where()->table("ticket")->render(["id"=>1])
);