<?php

require_once __DIR__ . "/../vendor/autoload.php";

if (is_readable(__DIR__ . '/'. '.env.test')) {
    $dotenv = new Dotenv\Dotenv(__DIR__, '.env.test');
    $dotenv->load();
}
