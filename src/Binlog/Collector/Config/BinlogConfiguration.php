<?php

namespace Binlog\Collector\Config;

use Binlog\Collector\BinlogHistoryService;
use Binlog\Collector\External\ExceptionHandlerInterface;
use Binlog\Collector\External\RowEventValueSkipperInterface;
use Binlog\Collector\Interfaces\BinlogHistoryServiceInterface;
use Binlog\Collector\OnceBinlogHistoryService;
use MySQLReplication\Config\Config;
use MySQLReplication\Config\ConfigService;

/**
 * Class BinlogConfiguration
 * @package Binlog\Collector\Config
 */
class BinlogConfiguration
{
	/** @var array */
	public $argv;
	/** @var BinlogEnvConfig */
	public $binlog_env_config;
	/** @var ExceptionHandlerInterface */
	public $exception_handler;
	/** @var BinlogHistoryServiceInterface */
	public $binlog_history_service;
	/** @var RowEventValueSkipperInterface|null */
	public $row_event_value_skipper;

	/**
	 * BinlogConfiguration constructor.
	 *
	 * @param BinlogEnvConfig                    $binlog_env_config
	 * @param ExceptionHandlerInterface          $exception_handler
	 * @param BinlogHistoryServiceInterface      $binlog_history_service
	 * @param RowEventValueSkipperInterface|null $row_event_value_skipper
	 * @param array                              $argv
	 */
	private function __construct(
		array $argv,
		BinlogEnvConfig $binlog_env_config,
		ExceptionHandlerInterface $exception_handler,
		BinlogHistoryServiceInterface $binlog_history_service,
		RowEventValueSkipperInterface $row_event_value_skipper = null
	) {
		$this->binlog_env_config = $binlog_env_config;
		$this->exception_handler = $exception_handler;
		$this->binlog_history_service = $binlog_history_service;
		$this->row_event_value_skipper = $row_event_value_skipper;
		$this->argv = $argv;
	}

	public static function newInstance(
		array $argv,
		BinlogEnvConfig $binlog_env_config,
		ExceptionHandlerInterface $exception_handler
	): self {
		$self = new self(
			$argv,
			$binlog_env_config,
			$exception_handler,
			new BinlogHistoryService(),
			$binlog_env_config->row_event_value_skipper
		);

		return $self;
	}

	public static function newInstanceForOnce(
		array $argv,
		BinlogEnvConfig $binlog_env_config,
		ExceptionHandlerInterface $exception_handler
	): self {
		$self = new self(
			$argv,
			$binlog_env_config,
			$exception_handler,
			new OnceBinlogHistoryService(),
			$binlog_env_config->row_event_value_skipper
		);

		return $self;
	}

	public function createPartitionerConfig(): BinlogPartitionerConfig
	{
		return BinlogPartitionerConfig::create(
			$this->binlog_env_config->binlog_connect_array,
			$this->binlog_env_config->binlog_config_array
		);
	}

	public function createWorkerConfig(): BinlogWorkerConfig
	{
		return BinlogWorkerConfig::create(
			$this->binlog_env_config->binlog_connect_array,
			$this->binlog_env_config->binlog_config_array
		);
	}

	public function extendWorkerConfig(
		array $replace_connect_array,
		array $replace_config_array
	): BinlogWorkerConfig {
		$new_connect_array = array_merge($this->binlog_env_config->binlog_connect_array, $replace_connect_array);
		$new_config_array = array_merge($this->binlog_env_config->binlog_config_array, $replace_config_array);

		return BinlogWorkerConfig::create($new_connect_array, $new_config_array);
	}

	public function createConnectConfig(): Config
	{
		return (new ConfigService())->makeConfigFromArray($this->binlog_env_config->binlog_connect_array);
	}

	public static function createCustomConnectConfigWithReplace(
		array $binlog_connect_array,
		array $replace_connect_array
	): Config {
		$new_connect_array = array_merge($binlog_connect_array, $replace_connect_array);

		return (new ConfigService())->makeConfigFromArray($new_connect_array);
	}
}
