<?php

namespace Binlog\Collector;

use Binlog\Collector\Interfaces\BinlogHistoryServiceInterface;
use Monolog\Logger;
use MySQLReplication\Config\Config;
use Binlog\Collector\Model\ReplicationDbModel;

/**
 * Class BinlogHistoryGtidChildUpdater
 * @package Binlog\Collector
 */
class BinlogHistoryGtidChildUpdater
{
	const CHILD_MAX_ALLOW_ERROR_COUNT = 10;
	const CHILD_ONCE_BINLOG_FETCH_LIMIT = 1000;
	const MAX_COUNT_PER_CHILD = 100000;
	const MIN_COUNT_PER_CHILD = 1000;

	/** @var ReplicationQuery */
	private $replication_query;
	/** @var ReplicationDbModel */
	private $replication_db_model;
	/** @var Logger */
	private $logger;
	/** @var int */
	private $error_count;
	/** @var int */
	private $remain_binlog_count;
	/** @var BinlogHistoryServiceInterface */
	private $binlog_history_service;


	public function __construct(Logger $logger, Config $config)
	{
		$this->logger = $logger;
		$this->replication_db_model = new ReplicationDbModel($config);
		$this->replication_query = new ReplicationQuery($this->replication_db_model);
		$this->binlog_history_service = new BinlogHistoryService();
	}

	private function getFetchCount(): int
	{
		if ($this->remain_binlog_count <= 0) {
			return 0;
		}
		$fetch_count = self::CHILD_ONCE_BINLOG_FETCH_LIMIT;
		if ($this->remain_binlog_count < self::CHILD_ONCE_BINLOG_FETCH_LIMIT) {
			$fetch_count = $this->remain_binlog_count;
		}
		$this->remain_binlog_count -= $fetch_count;

		return $fetch_count;
	}

	public function execute(int $last_binlog_id, int $max_binlog_count)
	{
		$this->error_count = 0;
		$this->remain_binlog_count = $max_binlog_count;
		$fetch_count = $this->getFetchCount();
		$is_first = true;
		while ($fetch_count > 0) {
			//최초 $last_binlog_id 포함
			if ($is_first) {
				$dicts = $this->binlog_history_service->getEmptyGtidBinlogDictsByLesserEqualId(
					$last_binlog_id,
					$fetch_count
				);
				$is_first = false;
			} else {
				$dicts = $this->binlog_history_service->getEmptyGtidBinlogDictsByLesserId(
					$last_binlog_id,
					$fetch_count
				);
			}
			$is_all_updated = $this->calculateGtidAndUpdate($dicts);
			if (!$is_all_updated) {
				break;
			}
			$dict_count = count($dicts);
			$last_binlog_id = $dicts[$dict_count - 1]['id'];
			$fetch_count = $this->getFetchCount();
		}
	}

	/**
	 * @param array $dicts
	 *
	 * @return bool
	 */
	private function calculateGtidAndUpdate(array $dicts): bool
	{
		foreach ($dicts as $dict) {
			$id = intval($dict['id']);
			$binlog_file_name = $dict['binlog_filename'];
			$binlog_position = intval($dict['gtid_end_pos']);
			try {
				$binlog_offset_dto = $this->replication_query->convertToBinlogOffsetDto(
					$binlog_file_name,
					$binlog_position
				);
				$this->binlog_history_service->updateBinlogGtid($id, $binlog_offset_dto->mariadb_gtid);
			} catch (\Exception $exception) {
				$this->error_count++;
				if ($this->error_count >= self::CHILD_MAX_ALLOW_ERROR_COUNT) {
					$this->logger->info('error 개수가 ' . $this->error_count . '개가 넘음');

					return false;
				}
			}
		}

		return true;
	}

	public function close()
	{
		$this->replication_db_model->close();
	}
}
