<?php

require_once __DIR__ . "/../include/bootstrap_binlog_collector.php";

use Binlog\Collector\Application\BinlogCollectorApplication;
use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\External\Impl\DefaultRowEventValueSkipper;
use Binlog\Collector\External\Impl\DefaultSentryExceptionHandler;
use Binlog\Collector\Library\FileLock;
use Binlog\Collector\Monitor\Constants\TimeMonitorConst;
use Binlog\Collector\Monitor\TimeMonitor;

$lock = new FileLock(basename(__FILE__));
if (!$lock->tryLock()) {
	die();
}

$tables_only = ['test_target1', 'test_target2', 'test_target3', 'test_target4'];
$databasesOnly = ['binlog_sample1','binlog_sample2','binlog_sample3','binlog_sample4'];

$env_config = BinlogEnvConfig::extendDefaultConfig(
	[],
	new DefaultRowEventValueSkipper($tables_only, $databasesOnly)
);

$env_config->validateTarget();
$exception_handler = new DefaultSentryExceptionHandler('/var/log/ridi/', 'binlog_collector', $env_config);
$configuration = BinlogConfiguration::newInstance($argv, $env_config, $exception_handler);

$function = function () use ($configuration) {
	$application = new BinlogCollectorApplication($configuration);
	$application->executeWorking();
};
TimeMonitor::benchmark(TimeMonitorConst::TYPE_BINLOG_WORKER, $function);

$lock->unlock();
