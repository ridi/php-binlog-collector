<?php

require_once __DIR__ . "/../include/bootstrap_binlog_collector.php";

use Binlog\Collector\Application\BinlogCollectorApplication;
use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\External\Impl\DefaultSentryExceptionHandler;
use Binlog\Collector\Library\FileLock;
use Binlog\Collector\Monitor\Constants\TimeMonitorConst;
use Binlog\Collector\Monitor\TimeMonitor;

$lock = new FileLock(basename(__FILE__));
if (!$lock->tryLock()) {
    die();
}
$env_config = BinlogEnvConfig::importDefaultConfig();
$exception_handler = new DefaultSentryExceptionHandler('./', 'binlog_collector', $env_config);
$configuration = BinlogConfiguration::newInstance($argv, $env_config, $exception_handler);

$function = function () use ($configuration): void {
    $application = new BinlogCollectorApplication($configuration);
    $application->executePartitioning();
};
TimeMonitor::benchmark(TimeMonitorConst::TYPE_BINLOG_PARTITIONER, $function);

$lock->unlock();
