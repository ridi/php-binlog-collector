<?php

require_once __DIR__ . "/../include/bootstrap_binlog_collector.php";

use Binlog\Collector\Application\BinlogCollectorApplication;
use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\External\Impl\DefaultSentryExceptionHandler;
use Binlog\Collector\Library\FileLock;

$lock = new FileLock(basename(__FILE__));
if (!$lock->tryLock()) {
	die();
}
$env_config = BinlogEnvConfig::extendDefaultConfig(
	[
		'gtid_partition_max_count' => 250,
		'jump_offset_for_next_partition' => 1000
	]
);
$exception_handler = new DefaultSentryExceptionHandler('/var/log/ridi/', 'binlog_collector', $env_config);
$configuration = BinlogConfiguration::newInstance($argv, $env_config, $exception_handler);

$application = new BinlogCollectorApplication($configuration);
$application->getInfo();

$lock->unlock();



