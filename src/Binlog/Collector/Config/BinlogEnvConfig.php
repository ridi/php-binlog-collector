<?php

namespace Binlog\Collector\Config;

use Binlog\Collector\Exception\MsgException;
use Binlog\Collector\External\RowEventValueSkipperInterface;

/**
 * Class BinlogEnvConfig
 * @package Binlog\Collector\Config
 */
class BinlogEnvConfig
{
	const TARGET_DB = 'target_db';
	const HISTORY_WRITE_DB = 'history_write_db';

	const CHILD_SLAVE_PREFIX_ID = '500';
	const ONCE_CHILD_SLAVE_PREFIX_ID = '600';
	const CHILD_TEMPORARY_SLAVE_ID = 999;
	const ONCE_CHILD_TEMPORARY_SLAVE_ID = 998;

	/** @var bool */
	public $enable_sentry;
	/** @var string */
	public $sentry_key;
	/** @var array */
	public $binlog_connect_array;
	/** @var array */
	public $binlog_config_array;
	/** @var RowEventValueSkipperInterface|null */
	public $row_event_value_skipper;

	public static function getTargetBinlogDbParams()
	{
		return [
			'host' => getenv('TARGET_DB_HOST'),
			'user' => getenv('TARGET_DB_USER'),
			'port' => getenv('TARGET_DB_PORT'),
			'password' => getenv('TARGET_DB_PASSWORD'),
			'dbname' => getenv('TARGET_DB_DBNAME'),
			'driver' => getenv('TARGET_DB_DRIVER'),
			'charset' => getenv('TARGET_DB_CHARSET'),
			'driverOptions' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
		];
	}

	public static function getHistoryWriteDbParams()
	{
		return [
			'host' => getenv('HISTORY_WRITE_DB_HOST'),
			'user' => getenv('HISTORY_WRITE_DB_USER'),
			'port' => getenv('HISTORY_WRITE_DB_PORT'),
			'password' => getenv('HISTORY_WRITE_DB_PASSWORD'),
			'dbname' => getenv('HISTORY_WRITE_DB_DBNAME'),
			'driver' => getenv('HISTORY_WRITE_DB_DRIVER'),
			'charset' => getenv('HISTORY_WRITE_DB_CHARSET'),
			'driverOptions' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
		];
	}

	public static function getConnectionParams($name): array
	{
		switch ($name) {
			case self::TARGET_DB:
				return self::getTargetBinlogDbParams();
			case self::HISTORY_WRITE_DB:
				return self::getHistoryWriteDbParams();
		}

		throw new \Exception("{$name} DB connection parameters are missing.");
	}

	public static function importDefaultConfig(RowEventValueSkipperInterface $row_event_value_skipper = null): self
	{
		$binlog_env_config = self::extendDefaultConfig([], $row_event_value_skipper);

		return $binlog_env_config;
	}

	public static function extendDefaultConfig(
		array $replace_binlog_config_array,
		RowEventValueSkipperInterface $row_event_value_skipper = null
	): self {

		$default_binlog_connect_array = self::getDefaultBinlogConnectArray();
		$default_binlog_config_array = self::getDefaultBinlogConfigArray();

		$replace_binlog_connect_array = [];
		if ($row_event_value_skipper !== null) {
			$replace_binlog_connect_array['tablesOnly'] = $row_event_value_skipper->getTablesOnly();
			$replace_binlog_connect_array['databasesOnly'] = $row_event_value_skipper->getDatabasesOnly();

		}

		$self = new self();
		$self->enable_sentry = getenv('ENABLE_SENTRY');
		$self->sentry_key = getenv('SENTRY_KEY');
		$self->binlog_connect_array = array_merge($default_binlog_connect_array, $replace_binlog_connect_array);
		$self->binlog_config_array = array_merge($default_binlog_config_array, $replace_binlog_config_array);
		$self->row_event_value_skipper = $row_event_value_skipper;

		return $self;
	}

	private static function getDefaultBinlogConnectArray(): array
	{
		return [
			'ip' => BinlogEnvConfig::getTargetBinlogDbParams()['host'],
			'port' => BinlogEnvConfig::getTargetBinlogDbParams()['port'],
			'user' => BinlogEnvConfig::getTargetBinlogDbParams()['user'],
			'password' => BinlogEnvConfig::getTargetBinlogDbParams()['password'],
			'charset' => BinlogEnvConfig::getTargetBinlogDbParams()['charset'],
			'tablesOnly' => [],
			'databasesOnly' => []
		];
	}

	private static function getDefaultBinlogConfigArray(): array
	{
		return [
			'gtid_partition_max_count' => 1000,
			'jump_offset_for_next_partition' => 10000,
			'is_all_print_event' => false,
			'child_process_max_count' => 10,
			'once_processed_max_event_count_in_gtid' => 100,
			'gtid_count_for_persist_per_partition' => 500
		];
	}

	public function validateTarget()
	{
		if (count($this->binlog_connect_array['tablesOnly']) === 0) {
			throw new MsgException('tablesOnly is empty');
		}
		if (count($this->binlog_connect_array['databasesOnly']) === 0) {
			throw new MsgException('databasesOnly is empty');
		}
	}
}
