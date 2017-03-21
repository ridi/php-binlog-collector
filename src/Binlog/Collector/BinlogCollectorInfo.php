<?php

namespace Binlog\Collector;

use Binlog\Collector\Config\BinlogConfiguration;
use Binlog\Collector\Config\BinlogPartitionerConfig;
use Binlog\Collector\Exception\MsgException;
use Binlog\Collector\Model\ReplicationDbModel;
use Monolog\Logger;

/**
 * Class BinlogCollectorInfo
 * @package Binlog\Collector
 */
class BinlogCollectorInfo
{
	/** @var Logger */
	private $logger;

	/**
	 * @param Logger                  $logger
	 * @param BinlogPartitionerConfig $partitioner_config
	 * @param array                   $argv
	 *
	 */
	public function getInfo(Logger $logger, BinlogPartitionerConfig $partitioner_config, array $argv)
	{
		$this->logger = $logger;
		try {
			$this->assertInfoCommand($argv);
		} catch (MsgException $e) {
			print('error: ' . $e->getMessage() . "\n");
			$this->printGetInfoUsage($argv[0]);
			exit();
		}

		switch ($argv[1]) {
			case 'master_status':
				$replication_db_model = new ReplicationDbModel($partitioner_config->connect_config);
				$replication_query = new ReplicationQuery($replication_db_model);
				$master_binlog_offset_dto = $replication_query->getMasterBinlogOffset();
				$logger->info("only Query: Master Bin_Log_Offset: {$master_binlog_offset_dto}");
				exit;
			case 'gtid_to_binlog_pos':
				$this->printGtidToBinlogPos($partitioner_config->binlog_connect_array, $argv);
				exit;
		}
	}

	private function printGtidToBinlogPos(array $connect_array, array $argv)
	{
		$replace_connect_array = [];
		if (count($argv) > 3 && $argv[3] !== 'current') {
			$replace_connect_array['ip'] = $argv[3];
		}
		$connect_config = BinlogConfiguration::createCustomConnectConfigWithReplace(
			$connect_array,
			$replace_connect_array
		);
		try {
			$replication_db_model = new ReplicationDbModel($connect_config);
			$replication_query = new ReplicationQuery($replication_db_model);
			// check connection and auth
			$replication_query->assertCheckAuth();
			$master_binlog_offset_dto = $replication_query->getMasterBinlogOffset();

			$pos_finder = new BinlogPosFinder($this->logger, $replication_db_model);

			$finder_dto = null;
			switch (count($argv)) {
				case 3:
				case 4:
					$finder_dto = $pos_finder->findBinlogOffsetDto($master_binlog_offset_dto, $argv[2]);
					break;
				case 5:
					$finder_dto = $pos_finder->findBinlogOffsetDto($master_binlog_offset_dto, $argv[2], $argv[4]);
					break;
				case 6:
					$finder_dto = $pos_finder->findBinlogOffsetDto(
						$master_binlog_offset_dto,
						$argv[2],
						$argv[4],
						intval($argv[5])
					);
					break;
			}
			$this->logger->info("gtid_to_binlog_pos's result: {$finder_dto}");
			$replication_db_model->close();
		} catch (\Throwable $e) {
			$this->logger->info("error: " . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * @param array $argv
	 *
	 * @throws MsgException
	 */
	private function assertInfoCommand(array $argv)
	{
		if (count($argv) >= 2) {
			switch ($argv[1]) {
				case 'gtid_to_binlog_pos':
					if (count($argv) < 3 || 6 < count($argv)) {
						throw new MsgException('wrong command');
					}

					return;
				case 'master_status':
					return;
				default:
					throw new MsgException('wrong command');
			}
		}

		throw new MsgException('wrong command');
	}

	private function printGetInfoUsage(string $php_file)
	{
		print("##########################################################################################\n");
		print("Usage:\n");

		print("1. master 현재 위치 조회\n");
		print("    php {$php_file} master_status\n");
		print("\n");

		print("2. Gtid To Binlog Pos 변환\n");
		print("    php {$php_file} gtid_to_binlog_pos [gtid] [ip=current] [skip_server_id=none] [limitPreviousFileCount=5]\n");
		print("ex) php {$php_file} gtid_to_binlog_pos 0-43-14543397 current none 10\n");
		print("##########################################################################################\n");
	}
}
