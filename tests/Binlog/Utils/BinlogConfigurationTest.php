<?php

namespace Binlog\Tests\Utils;

use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogEnvConfig;
use Binlog\Collector\External\Impl\DefaultRowEventValueSkipper;
use Binlog\Collector\External\Impl\DefaultSentryExceptionHandler;
use PHPUnit\Framework\TestCase;

class BinlogConfigurationTest extends TestCase
{
    public function testCreateConnectConfigWithReplace(): void
    {
        $tables_only = ['table'];
        $databases_only = ['database'];

        $binlog_env_config = BinlogEnvConfig::extendDefaultConfig(
            [],
            new DefaultRowEventValueSkipper($tables_only, $databases_only)
        );
        $exception_handler = new DefaultSentryExceptionHandler('./', 'binlog_collector', $binlog_env_config);

        $binlog_configuration = BinlogConfiguration::newInstanceForOnce([], $binlog_env_config, $exception_handler);
        $new_binlog_worker_config = $binlog_configuration->extendWorkerConfig(
            [
                'slaveId' => '999',
                'ip' => '127.0.0.2',
            ],
            [
                'child_index' => 1,
            ]
        );
        $this->assertEquals('127.0.0.2', $new_binlog_worker_config->connect_config->getHost());
        $this->assertEquals('999', $new_binlog_worker_config->connect_config->getSlaveId());
        $this->assertEquals(1, $new_binlog_worker_config->child_index);
    }
}
