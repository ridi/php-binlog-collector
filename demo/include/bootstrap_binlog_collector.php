<?php

require_once __DIR__ . "/../../vendor/autoload.php";

if (is_readable(__DIR__ . '/'. '.env')) {
	$dotenv = new Dotenv\Dotenv(__DIR__, '.env');
	$dotenv->load();
} elseif (is_readable(__DIR__ . '/'. '.env.local')) {
	$dotenv = new Dotenv\Dotenv(__DIR__, '.env.local');
	$dotenv->load();
}

date_default_timezone_set('Asia/Seoul');
error_reporting(E_ALL & ~E_NOTICE);
set_time_limit(0);
ini_set('mysql.connect_timeout', -1);
ini_set('default_socket_timeout', -1);
ini_set('max_execution_time', -1);
ini_set('memory_limit', '2048M');
