<?php

require_once __DIR__ . "/../include/bootstrap_binlog_collector.php";

use Binlog\Collector\Application\BinlogHistoryGtidUpdaterApplication;
use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\External\Impl\DefaultSentryExceptionHandler;
use Binlog\Collector\Library\FileLock;

$lock = new FileLock(basename(__FILE__));
if (!$lock->tryLock()) {
	die();
}
$env_config = BinlogEnvConfig::importDefaultConfig();
$exception_handler = new DefaultSentryExceptionHandler('/var/log/ridi/', 'binlog_history_gtid_updater', $env_config);
$configuration = BinlogConfiguration::newInstance($argv, $env_config, $exception_handler);

$application = new BinlogHistoryGtidUpdaterApplication($configuration);
$application->executeUpdate();

$lock->unlock();
